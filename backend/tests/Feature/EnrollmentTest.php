<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
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
        $this->semester = Semester::first();
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

    public function test_student_can_create_krs_draft(): void
    {
        // Delete any existing enrollment from seeder for clean test
        StudentEnrollment::where('student_id', $this->student->id)->forceDelete();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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
        $delResponse = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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
        $submitRes = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/submit");

        $submitRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'submitted']]);

        // 2. Approve (Switch to Admin user)
        auth()->forgetGuards();
        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/approve", [
                'notes' => 'KRS disetujui tanpa catatan.',
            ]);

        $approveRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'approved']]);

        // 3. Lock
        $lockRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lock");

        $lockRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'locked']]);
    }

    public function test_cannot_modify_approved_or_locked_krs(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::LOCKED]);

        $class = AcademicClass::where('code', 'MKU103-A')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
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

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson("/api/v1/enrollments/{$otherEnrollment->id}/available-classes");

        $response->assertStatus(403);
    }
}
