<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Assessment\Enums\ComponentType;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\AssessmentSchemeItem;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\CreditLimit;
use Modules\Curriculum\Models\Curriculum;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\KrsPackage;
use Modules\Enrollment\Models\KrsPackageItem;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Enrollment\Services\EnrollmentValidationService;
use Modules\Identity\Models\User;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected User $admin;
    protected User $studentUser;
    protected Student $student;
    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $this->studentUser->createToken('student_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first();
        // The whole KRS flow (classes, packages, seeded enrollments) is bound to the
        // ACTIVE semester, and students may only create a KRS for it.
        $this->semester = Semester::where('status', 'active')->first() ?? Semester::first();

        // Keep the registration window deterministic for every KRS test: the
        // window-related cases below close it explicitly when they need to.
        $this->openKrsWindow();
    }

    /**
     * Open the KRS window of the active semester around "today".
     */
    protected function openKrsWindow(): void
    {
        $this->semester->update([
            'krs_start_date' => now()->subDays(30)->toDateString(),
            'krs_end_date' => now()->addDays(30)->toDateString(),
            'kprs_start_date' => null,
            'kprs_end_date' => null,
        ]);
    }

    /**
     * Attach a course to the student's active curriculum so the KRS curriculum
     * rule accepts it (a study program may only have one active curriculum).
     */
    protected function attachCourseToActiveCurriculum(Course $course): void
    {
        $curriculum = Curriculum::where('study_program_id', $this->student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->firstOrFail();

        $semester = $curriculum->semesters()->firstOrCreate(
            ['semester_number' => 1],
            ['name' => 'Semester 1']
        );

        $semester->subjects()->firstOrCreate(
            ['course_id' => $course->id],
            ['is_mandatory' => true]
        );
    }

    /**
     * Bind a tiered SKS limit ([{min_gpa,max_gpa,max_sks}]) to the student's
     * active curriculum so `credit_limits.rules` drives the ceiling.
     *
     * @param array<int, array<string, float|int>> $rules
     */
    protected function attachCreditLimitToActiveCurriculum(array $rules): CreditLimit
    {
        $creditLimit = CreditLimit::create([
            'name' => 'Batas SKS Berjenjang Uji',
            'rules' => $rules,
            'status' => 'active',
        ]);

        Curriculum::where('study_program_id', $this->student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->update(['credit_limit_id' => $creditLimit->id]);

        return $creditLimit;
    }

    protected function tieredRules(): array
    {
        return [
            ['min_gpa' => 3.00, 'max_gpa' => 4.00, 'max_sks' => 24],
            ['min_gpa' => 2.50, 'max_gpa' => 2.99, 'max_sks' => 21],
            ['min_gpa' => 2.00, 'max_gpa' => 2.49, 'max_sks' => 18],
            ['min_gpa' => 0.00, 'max_gpa' => 1.99, 'max_sks' => 15],
        ];
    }

    /**
     * Grade every component of the class's active assessment scheme with the same
     * score, so the computed final score equals $score (all weights sum to 100 and
     * every component is scored out of 100).
     */
    protected function seedGrade(Student $student, AcademicClass $class, float $score): void
    {
        $scheme = AssessmentScheme::where('academic_class_id', $class->id)
            ->where('is_active', true)
            ->first();

        if (!$scheme) {
            $component = AssessmentComponent::create([
                'academic_class_id' => $class->id,
                'name' => 'Ujian Akhir Semester',
                'code' => 'UAS',
                'type' => ComponentType::FINAL_EXAM,
                'max_score' => 100.00,
                'is_required' => true,
                'sequence' => 1,
            ]);

            $scheme = AssessmentScheme::create([
                'academic_class_id' => $class->id,
                'name' => 'Skema Penilaian Uji KRS',
                'status' => SchemeStatus::ACTIVE,
                'total_weight' => 100.00,
                'is_active' => true,
            ]);

            AssessmentSchemeItem::create([
                'assessment_scheme_id' => $scheme->id,
                'assessment_component_id' => $component->id,
                'weight' => 100.00,
            ]);
        }

        $componentIds = AssessmentSchemeItem::where('assessment_scheme_id', $scheme->id)
            ->pluck('assessment_component_id');

        foreach ($componentIds as $componentId) {
            StudentGrade::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_class_id' => $class->id,
                    'assessment_component_id' => $componentId,
                ],
                [
                    'score' => $score,
                    'graded_at' => now(),
                    'status' => GradeStatus::FINAL,
                ]
            );
        }
    }

    /**
     * The most recent semester BEFORE the active one — the KRS whose grades form
     * the student's previous-semester IPS.
     */
    protected function previousSemester(): Semester
    {
        return Semester::where('id', '!=', $this->semester->id)
            ->where('start_date', '<', $this->semester->start_date)
            ->orderByDesc('start_date')
            ->firstOrFail();
    }

    /**
     * Give the student a LOCKED KRS in the previous semester containing $course,
     * graded with $score. This is the real history that prerequisite checks and
     * the IPS-based SKS ceiling must be derived from.
     */
    protected function seedPreviousSemesterCourse(Student $student, Course $course, float $score): AcademicClass
    {
        $previous = $this->previousSemester();

        $class = AcademicClass::firstOrCreate(
            [
                'semester_id' => $previous->id,
                'course_id' => $course->id,
                'section' => 'P',
            ],
            [
                'study_program_id' => $student->study_program_id,
                'code' => "{$course->code}-PRIOR",
                'name' => "{$course->name} (Semester Lalu)",
                'capacity' => 30,
                'enrolled_count' => 0,
                'status' => ClassStatus::OPEN,
            ]
        );

        $this->seedGrade($student, $class, $score);

        $enrollment = StudentEnrollment::firstOrCreate(
            ['student_id' => $student->id, 'semester_id' => $previous->id],
            [
                'status' => EnrollmentStatus::LOCKED,
                'total_credits' => 0,
                'max_credits' => 24,
            ]
        );

        StudentEnrollmentItem::firstOrCreate(
            ['enrollment_id' => $enrollment->id, 'class_id' => $class->id],
            [
                'course_id' => $course->id,
                'credits' => (int) $course->credits,
                'status' => EnrollmentItemStatus::ENROLLED,
            ]
        );

        $enrollment->recalculateCredits();

        return $class;
    }

    protected function currentEnrollment(): StudentEnrollment
    {
        return StudentEnrollment::where('student_id', $this->student->id)
            ->where('semester_id', $this->semester->id)
            ->firstOrFail();
    }

    protected function tokenFor(string $email): string
    {
        return User::where('email', $email)->firstOrFail()->createToken('test')->plainTextToken;
    }

    /**
     * Act as another user.
     *
     * A feature test reuses one application instance for every request it makes,
     * so the resolved Sanctum guard (and therefore the authenticated user) is
     * cached between requests. Forgetting the guards whenever the actor changes
     * keeps each request authenticated as the intended user.
     */
    protected function asUser(string $token): self
    {
        auth()->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_student_can_create_krs_draft(): void
    {
        // Delete any existing enrollment from seeder for clean test
        StudentEnrollment::where('student_id', $this->student->id)->forceDelete();

        $response = $this->asUser($this->studentToken)
            ->postJson('/api/v1/enrollments', [
                'student_id' => $this->student->id,
                'semester_id' => $this->semester->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'status' => 'draft',
                    'total_credits' => 0,
                ],
            ]);
    }

    public function test_can_add_and_remove_class_item_with_capacity_and_credit_updates(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // MKU105-A is part of the student's active curriculum and is not enrolled yet.
        $class = AcademicClass::where('code', 'MKU105-A')->first();
        $initialCapacity = $class->enrolled_count;
        $initialCredits = $enrollment->fresh()->total_credits;
        $courseCredits = $class->course->credits;

        // Add class
        $response = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertEquals($initialCapacity + 1, $class->fresh()->enrolled_count);
        $this->assertEquals($initialCredits + $courseCredits, $enrollment->fresh()->total_credits);

        $item = $enrollment->items()->where('class_id', $class->id)->first();
        $this->assertNotNull($item);

        // Remove class
        $delResponse = $this->asUser($this->studentToken)
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$item->id}");

        $delResponse->assertStatus(200);
        $this->assertEquals($initialCapacity, $class->fresh()->enrolled_count);
        $this->assertEquals($initialCredits, $enrollment->fresh()->total_credits);
    }

    public function test_rejects_adding_duplicate_course_to_krs(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // PAI201 is already enrolled from seeder
        $class = AcademicClass::where('code', 'PAI201-A')->first();

        $response = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['course_id']]);
    }

    public function test_rejects_adding_class_when_capacity_is_full(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // Use a class that passes every other rule so the capacity rule is isolated.
        $class = AcademicClass::where('code', 'MKU103-A')->first();
        $class->update(['capacity' => 10, 'enrolled_count' => 10]);

        $response = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['class_id']])
            ->assertJsonFragment(['errors' => ['class_id' => ["Kapasitas kelas {$class->code} sudah penuh (10/10 mahasiswa)."]]]);
    }

    public function test_rejects_schedule_conflict_for_student(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // Student already has PAI201 (Mon 08:00 - 10:30)
        // Create a new class with conflicting schedule (Mon 09:00 - 11:00)
        $newCourse = Course::create([
            'code' => 'TEST-CONFLICT-MK',
            'name' => 'Mata Kuliah Uji Bentrok',
            'credits' => 2,
            'theory_credits' => 2,
            'practical_credits' => 0,
        ]);

        // The course must live in the student's active curriculum, otherwise the
        // curriculum rule would fail before the schedule rule is evaluated.
        $this->attachCourseToActiveCurriculum($newCourse);

        $newClass = AcademicClass::create([
            'semester_id' => $this->semester->id,
            'course_id' => $newCourse->id,
            'code' => 'CONFLICT-A',
            'name' => 'Mata Kuliah Uji Bentrok - A',
            'section' => 'A',
            'capacity' => 30,
            'status' => ClassStatus::OPEN,
        ]);

        ClassSchedule::create([
            'class_id' => $newClass->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $newClass->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['class_id']])
            ->assertJsonPath('errors.class_id.0', fn (string $message) => str_contains($message, 'Schedule conflict'));
    }

    public function test_krs_workflow_submit_approve_and_lock(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // 1. Submit
        $submitRes = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/submit");

        $submitRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'submitted']]);

        // 2. Approve (Switch to Admin user)
        auth()->forgetGuards();
        $approveRes = $this->asUser($this->adminToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/approve", [
                'notes' => 'KRS disetujui tanpa catatan.',
            ]);

        $approveRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'approved']]);

        // 3. Lock
        $lockRes = $this->asUser($this->adminToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lock");

        $lockRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'locked']]);
    }

    public function test_cannot_modify_approved_or_locked_krs(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::LOCKED]);

        $class = AcademicClass::where('code', 'MKU103-A')->first();

        $response = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['enrollment']]);
    }

    public function test_available_classes_endpoint_flags_eligibility(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $response = $this->asUser($this->studentToken)
            ->getJson("/api/v1/enrollments/{$enrollment->id}/available-classes");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'course', 'is_eligible', 'eligibility_reasons'],
                ],
            ]);

        $rows = collect($response->json('data'))->keyBy('code');

        // A class from the student's own curriculum is selectable.
        $this->assertTrue($rows['MKU105-A']['is_eligible']);
        $this->assertEmpty($rows['MKU105-A']['eligibility_reasons']);

        // A class whose course is already in the KRS is reported as such.
        $this->assertFalse($rows['PAI201-A']['is_eligible']);
        $this->assertStringContainsString('sudah terdaftar di dalam KRS', $rows['PAI201-A']['eligibility_reason']);

        // Classes from other study programs are not offered to the student at all.
        $this->assertArrayNotHasKey('HKI201-A', $rows->all());
        $this->assertArrayNotHasKey('ES201-A', $rows->all());
    }

    public function test_available_classes_endpoint_hides_other_students_enrollment(): void
    {
        $otherStudent = Student::where('student_number', '202501002')->first();
        $otherEnrollment = StudentEnrollment::where('student_id', $otherStudent->id)->first();

        $response = $this->asUser($this->studentToken)
            ->getJson("/api/v1/enrollments/{$otherEnrollment->id}/available-classes");

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Gap #13 — the SKS ceiling must come from credit_limits.rules (IPS tiers)
    // ---------------------------------------------------------------------

    public function test_sks_ceiling_follows_the_ips_tier_of_the_credit_limit_rules(): void
    {
        $this->attachCreditLimitToActiveCurriculum($this->tieredRules());

        // Semester pertama (belum punya IPS) => tier paling atas.
        $this->assertSame(24, app(EnrollmentValidationService::class)->getMaxCredits($this->student));

        $mku101 = Course::where('code', 'MKU-101')->firstOrFail();

        // IPS 1.00 (nilai 50 = D) => tier 15 SKS.
        $this->seedPreviousSemesterCourse($this->student, $mku101, 50.0);
        $this->assertSame(15, app(EnrollmentValidationService::class)->getMaxCredits($this->student));

        // IPS 2.50 (nilai 62 = C+) => tier 21 SKS.
        $this->seedPreviousSemesterCourse($this->student, $mku101, 62.0);
        $this->assertSame(21, app(EnrollmentValidationService::class)->getMaxCredits($this->student));

        // IPS 3.00 (nilai 70 = B) => tier 24 SKS.
        $this->seedPreviousSemesterCourse($this->student, $mku101, 70.0);
        $this->assertSame(24, app(EnrollmentValidationService::class)->getMaxCredits($this->student));
    }

    public function test_sks_ceiling_falls_back_to_the_global_setting_without_a_credit_limit(): void
    {
        // No credit_limits bound to the curriculum => global `max_sks` setting.
        $this->assertSame(
            24,
            app(EnrollmentValidationService::class)->getMaxCredits($this->student),
            'Without a credit limit the global max_sks setting must apply'
        );
    }

    public function test_adding_classes_is_blocked_by_the_ips_based_sks_ceiling(): void
    {
        $this->attachCreditLimitToActiveCurriculum($this->tieredRules());

        // Low IPS in the previous semester => this student may only take 15 SKS.
        $this->seedPreviousSemesterCourse($this->student, Course::where('code', 'MKU-101')->firstOrFail(), 50.0);

        StudentEnrollment::where('student_id', $this->student->id)
            ->where('semester_id', $this->semester->id)
            ->forceDelete();

        $created = $this->asUser($this->studentToken)
            ->postJson('/api/v1/enrollments', [
                'student_id' => $this->student->id,
                'semester_id' => $this->semester->id,
            ]);

        $created->assertStatus(201)->assertJsonPath('data.max_credits', 15);

        $enrollment = $this->currentEnrollment();
        $this->assertSame(15, (int) $enrollment->max_credits);
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // 3 + 2 + 2 + 2 + 2 + 3 = 14 SKS, all conflict-free and all part of the
        // student's curriculum. One more 2-SKS course would need 16 SKS.
        foreach (['PAI201-A', 'MKU101-A', 'MKU102-A', 'MKU103-A', 'MKU105-A', 'PAI203-A'] as $code) {
            $class = AcademicClass::where('code', $code)->firstOrFail();
            $this->asUser($this->studentToken)
                ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $class->id])
                ->assertStatus(201);
        }

        $this->assertSame(14, (int) $enrollment->fresh()->total_credits);

        $extra = Course::where('code', 'PAI-202')->firstOrFail();
        $extraClass = AcademicClass::create([
            'semester_id' => $this->semester->id,
            'course_id' => $extra->id,
            'study_program_id' => $this->student->study_program_id,
            'code' => 'PAI202-A',
            'name' => 'Bahasa Arab - Kelas A',
            'section' => 'A',
            'capacity' => 30,
            'enrolled_count' => 0,
            'status' => ClassStatus::OPEN,
        ]);

        // 14 + 2 = 16 SKS > the 15-SKS tier this student's IPS entitles them to.
        $blocked = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $extraClass->id]);

        $blocked->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credits']])
            ->assertJsonPath('errors.credits.0', fn (string $m) => str_contains($m, 'Melebihi batas maksimal (15 SKS)'));

        $this->assertSame(14, (int) $enrollment->fresh()->total_credits);
    }

    // ---------------------------------------------------------------------
    // Gap #14 — the per-enrollment max_credits column must be enforced
    // ---------------------------------------------------------------------

    public function test_per_enrollment_max_credits_is_enforced_and_can_be_raised(): void
    {
        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT, 'max_credits' => 8]);

        // Seeder gives this KRS 7 SKS (PAI201 3 + MKU101 2 + MKU102 2).
        $this->assertSame(7, (int) $enrollment->fresh()->total_credits);

        $class = AcademicClass::where('code', 'MKU105-A')->firstOrFail();

        // 7 + 2 = 9 SKS > the stored quota of 8 => rejected, quoting the quota.
        $blocked = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $class->id]);

        $blocked->assertStatus(422)
            ->assertJsonStructure(['errors' => ['credits']])
            ->assertJsonPath('errors.credits.0', fn (string $m) => str_contains($m, 'Melebihi batas maksimal (8 SKS)'));

        // The academic office raises the quota through the advisor-quota endpoint.
        $this->asUser($this->adminToken)
            ->putJson("/api/v1/enrollments/{$enrollment->id}/advisor-quota", ['max_credits' => 10])
            ->assertStatus(200)
            ->assertJsonPath('data.max_credits', 10);

        // The very same addition now succeeds: raising the quota has a real effect.
        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $class->id])
            ->assertStatus(201);

        $this->assertSame(9, (int) $enrollment->fresh()->total_credits);
    }

    // ---------------------------------------------------------------------
    // Gap #15 — prerequisites must be backed by a PASSING grade
    // ---------------------------------------------------------------------

    public function test_prerequisite_is_rejected_when_the_grade_is_failing(): void
    {
        $mku103 = Course::where('code', 'MKU-103')->firstOrFail();
        $mku104 = Course::where('code', 'MKU-104')->firstOrFail();

        // MKU-104 requires MKU-103 with minimum grade C (see CourseSeeder).
        $this->assertTrue($mku104->prerequisites()->pluck('courses.id')->contains($mku103->id));

        // The student took MKU-103 last semester but FAILED it (score 30 => E).
        $this->seedPreviousSemesterCourse($this->student, $mku103, 30.0);

        $target = AcademicClass::create([
            'semester_id' => $this->semester->id,
            'course_id' => $mku104->id,
            'study_program_id' => $this->student->study_program_id,
            'code' => 'MKU104-A',
            'name' => 'Bahasa Arab II - Kelas A',
            'section' => 'A',
            'capacity' => 30,
            'enrolled_count' => 0,
            'status' => ClassStatus::OPEN,
        ]);

        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $target->id])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['prerequisite']]);

        // Same course, now actually passed (score 70 => B, above the required C).
        $this->seedPreviousSemesterCourse($this->student, $mku103, 70.0);

        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $target->id])
            ->assertStatus(201);
    }

    public function test_prerequisite_is_not_satisfied_by_an_ungraded_prior_enrollment(): void
    {
        $mku103 = Course::where('code', 'MKU-103')->firstOrFail();
        $mku104 = Course::where('code', 'MKU-104')->firstOrFail();

        // Prior KRS contains MKU-103 but no grade was ever recorded for it.
        $previous = $this->previousSemester();
        $class = AcademicClass::create([
            'semester_id' => $previous->id,
            'course_id' => $mku103->id,
            'study_program_id' => $this->student->study_program_id,
            'code' => 'MKU103-PRIOR',
            'name' => 'Bahasa Arab I (Semester Lalu)',
            'section' => 'P',
            'capacity' => 30,
            'enrolled_count' => 1,
            'status' => ClassStatus::OPEN,
        ]);

        $priorEnrollment = StudentEnrollment::create([
            'student_id' => $this->student->id,
            'semester_id' => $previous->id,
            'status' => EnrollmentStatus::LOCKED,
            'total_credits' => 0,
            'max_credits' => 24,
        ]);

        StudentEnrollmentItem::create([
            'enrollment_id' => $priorEnrollment->id,
            'class_id' => $class->id,
            'course_id' => $mku103->id,
            'credits' => (int) $mku103->credits,
            'status' => EnrollmentItemStatus::ENROLLED,
        ]);
        $priorEnrollment->recalculateCredits();

        $this->assertSame(
            0,
            StudentGrade::where('academic_class_id', $class->id)->count(),
            'The prior class must have no grade recorded at all'
        );

        $provider = app(\Modules\Enrollment\Services\AcademicHistoryProvider::class);
        $this->assertNotContains(
            $mku103->id,
            $provider->getPassedCourseIds($this->student, $this->semester->id),
            'A course without any recorded grade must not count as passed'
        );
        $this->assertFalse($provider->hasPassedPrerequisites($this->student, $mku104->id, $this->semester->id));
    }

    // ---------------------------------------------------------------------
    // Gap #16 — submit (and removal) must re-check the KRS window
    // ---------------------------------------------------------------------

    public function test_submit_is_rejected_after_the_krs_window_closed(): void
    {
        $this->semester->update([
            'krs_start_date' => now()->subDays(30)->toDateString(),
            'krs_end_date' => now()->subDay()->toDateString(),
            'kprs_start_date' => null,
            'kprs_end_date' => null,
        ]);

        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $submit = $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/submit");

        $submit->assertStatus(422)
            ->assertJsonStructure(['errors' => ['krs']])
            ->assertJsonPath('errors.krs.0', fn (string $m) => str_contains($m, 'Batas waktu (deadline) pengisian KRS'));

        $this->assertSame('draft', $enrollment->fresh()->status->value);

        // Dropping a class from the stale draft is blocked by the same window.
        $item = $enrollment->items()->where('status', EnrollmentItemStatus::ENROLLED->value)->firstOrFail();

        $this->asUser($this->adminToken)
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$item->id}")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['krs']]);

        // The KPRS (perubahan KRS) window reopens submission.
        $this->semester->update([
            'kprs_start_date' => now()->subDay()->toDateString(),
            'kprs_end_date' => now()->addDay()->toDateString(),
        ]);

        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    // ---------------------------------------------------------------------
    // Gap #17 — batal-tambah must leave a trail instead of hard-deleting
    // ---------------------------------------------------------------------

    public function test_batal_tambah_on_an_approved_krs_keeps_an_audit_trail(): void
    {
        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::APPROVED]);

        $class = AcademicClass::where('code', 'MKU101-A')->firstOrFail();
        $item = $enrollment->items()->where('class_id', $class->id)->firstOrFail();

        $creditsBefore = (int) $enrollment->fresh()->total_credits; // 7
        $seatsBefore = (int) $class->fresh()->enrolled_count;

        $this->asUser($this->adminToken)
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$item->id}", [
                'reason' => 'Jadwal bentrok dengan kerja praktik mahasiswa.',
            ])
            ->assertStatus(200);

        // The row is KEPT, flagged DROPPED and stamped as finalized.
        $this->assertDatabaseHas('student_enrollment_items', [
            'id' => $item->id,
            'status' => EnrollmentItemStatus::DROPPED->value,
        ]);
        $this->assertNotNull($item->fresh()->finalized_at);

        // Seat and study load are released.
        $this->assertSame($seatsBefore - 1, (int) $class->fresh()->enrolled_count);
        $this->assertSame($creditsBefore - 2, (int) $enrollment->fresh()->total_credits);

        // Dropped rows are excluded from the active study load.
        $this->assertSame(2, $enrollment->fresh()->activeItems()->count());
        $this->assertSame(1, $enrollment->fresh()->inactiveItems()->count());

        // Status history + audit log entries exist.
        $this->assertDatabaseHas('enrollment_status_histories', [
            'enrollment_id' => $enrollment->id,
            'enrollment_item_id' => $item->id,
            'action' => 'item_dropped',
            'from_status' => EnrollmentItemStatus::ENROLLED->value,
            'to_status' => EnrollmentItemStatus::DROPPED->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'item_dropped',
            'module' => 'Enrollment',
        ]);

        // A student may not withdraw a class from an already approved KRS.
        $other = $enrollment->fresh()->activeItems()->firstOrFail();
        $this->asUser($this->studentToken)
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$other->id}")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['enrollment']]);

        $this->assertSame(EnrollmentItemStatus::ENROLLED->value, $other->fresh()->status->value);
    }

    public function test_removing_from_a_draft_cancels_the_item_and_allows_re_adding(): void
    {
        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $class = AcademicClass::where('code', 'MKU105-A')->firstOrFail();
        $seatsBefore = (int) $class->fresh()->enrolled_count;

        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $class->id])
            ->assertStatus(201);

        $item = $enrollment->items()->where('class_id', $class->id)->firstOrFail();
        $itemId = $item->id;

        $this->asUser($this->studentToken)
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$itemId}")
            ->assertStatus(200);

        // The row survives as CANCELLED and no longer counts as study load.
        $this->assertDatabaseHas('student_enrollment_items', [
            'id' => $itemId,
            'status' => EnrollmentItemStatus::CANCELLED->value,
        ]);
        $this->assertSame($seatsBefore, (int) $class->fresh()->enrolled_count);
        $this->assertSame(0, $enrollment->fresh()->activeItems()->where('class_id', $class->id)->count());

        // Re-taking the same class reactivates the row instead of violating the
        // unique (enrollment_id, class_id) index.
        $this->asUser($this->studentToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", ['class_id' => $class->id])
            ->assertStatus(201);

        $this->assertSame(1, $enrollment->fresh()->items()->where('class_id', $class->id)->count());
        $this->assertSame(
            EnrollmentItemStatus::ENROLLED->value,
            $enrollment->fresh()->items()->where('class_id', $class->id)->firstOrFail()->status->value
        );
        $this->assertSame($seatsBefore + 1, (int) $class->fresh()->enrolled_count);
    }

    // ---------------------------------------------------------------------
    // Gap #18 — load-package must be scoped to the student's own DPA
    // ---------------------------------------------------------------------

    public function test_load_package_is_denied_for_a_lecturer_who_is_not_the_advisor(): void
    {
        $enrollment = $this->currentEnrollment();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $package = KrsPackage::create([
            'name' => 'Paket Semester 1 PAI',
            'study_program_id' => $this->student->study_program_id,
            'semester_level' => 1,
            'total_credits' => 2,
        ]);

        KrsPackageItem::create([
            'krs_package_id' => $package->id,
            'course_id' => Course::where('code', 'MKU-105')->firstOrFail()->id,
            'credits' => 2,
        ]);

        // Dr. Hj. Siti Fatimah is a `dosen` but NOT this student's DPA.
        $outsiderToken = $this->tokenFor('siti.fatimah@siakad.ac.id');

        $this->asUser($outsiderToken)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/load-package", [
                'krs_package_id' => $package->id,
            ])
            ->assertStatus(403);

        $mku105 = AcademicClass::where('code', 'MKU105-A')->firstOrFail();
        $this->assertSame(
            0,
            $enrollment->items()->where('class_id', $mku105->id)->count(),
            'A lecturer who is not the DPA must not be able to inject a package'
        );

        // The student's own DPA (dosen@siakad.ac.id -> Dr. Ahmad Dosen) may.
        $this->asUser($this->tokenFor('dosen@siakad.ac.id'))
            ->postJson("/api/v1/enrollments/{$enrollment->id}/load-package", [
                'krs_package_id' => $package->id,
            ])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.added')
            ->assertJsonCount(0, 'data.failed');

        $this->assertSame(1, $enrollment->fresh()->items()->where('class_id', $mku105->id)->count());
    }
}
