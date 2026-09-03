<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\ComponentType;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AssessmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $dosenToken;
    protected string $studentToken;
    protected User $admin;
    protected User $dosenUser;
    protected User $studentUser;
    protected AcademicClass $academicClass;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $course = \Modules\Course\Models\Course::first();
        $semester = \Modules\Academic\Models\Semester::where('status', 'active')->first() ?? \Modules\Academic\Models\Semester::first();
        $lecturer = \Modules\Lecturer\Models\Lecturer::first();

        $this->academicClass = AcademicClass::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'code' => 'TEST-GRADE-01',
            'name' => 'Kelas Uji Penilaian',
            'section' => 'T',
            'capacity' => 30,
            'status' => \Modules\Class\Enums\ClassStatus::OPEN,
        ]);

        $this->academicClass->classLecturers()->create([
            'lecturer_id' => $lecturer->id,
            'role' => \Modules\Class\Enums\ClassLecturerRole::PRIMARY,
        ]);

        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->student->user_id = $this->studentUser->id;
        $this->student->save();

        $enrollment = \Modules\Enrollment\Models\StudentEnrollment::firstOrCreate([
            'student_id' => $this->student->id,
            'semester_id' => $semester->id,
        ], [
            'status' => \Modules\Enrollment\Enums\EnrollmentStatus::APPROVED,
            'total_credits' => 3,
        ]);

        $enrollment->items()->firstOrCreate([
            'class_id' => $this->academicClass->id,
        ], [
            'course_id' => $course->id,
            'credits' => 3,
            'status' => \Modules\Enrollment\Enums\EnrollmentItemStatus::ENROLLED,
        ]);
    }

    public function test_full_academic_grading_lifecycle(): void
    {
        // 1. Dosen creates components
        $c1 = AssessmentComponent::create([
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Tugas Praktik Mandiri',
            'code' => 'PRAKTIK_1',
            'type' => ComponentType::ASSIGNMENT,
            'max_score' => 100.00,
            'is_required' => true,
        ]);

        $c2 = AssessmentComponent::create([
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Ujian Akhir Semester Praktik',
            'code' => 'UAS_PRAKTIK',
            'type' => ComponentType::FINAL_EXAM,
            'max_score' => 100.00,
            'is_required' => true,
        ]);

        // 2. Dosen creates & activates assessment scheme (50% + 50% = 100%)
        $schemePayload = [
            'name' => 'Skema Kurikulum Baru 2026',
            'items' => [
                ['assessment_component_id' => $c1->id, 'weight' => 50.00],
                ['assessment_component_id' => $c2->id, 'weight' => 50.00],
            ],
        ];

        \Laravel\Sanctum\Sanctum::actingAs($this->dosenUser);
        $schemeRes = $this->postJson("/api/v1/classes/{$this->academicClass->id}/assessment-scheme", $schemePayload);
        $schemeRes->assertStatus(201);
        $schemeId = $schemeRes->json('data.id');

        $activateRes = $this->postJson("/api/v1/assessment-schemes/{$schemeId}/activate");
        $activateRes->assertStatus(200);

        // 3. Dosen inputs student scores (90 and 80) => Final Score = (90*0.5) + (80*0.5) = 85.00 (A)
        $gradesPayload = [
            'grades' => [
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $c1->id,
                    'score' => 90.00,
                ],
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $c2->id,
                    'score' => 80.00,
                ],
            ],
        ];

        $gradeRes = $this->postJson("/api/v1/classes/{$this->academicClass->id}/grades", $gradesPayload);
        $gradeRes->assertStatus(200);

        // 4. Dosen submits grades for review
        $submitRes = $this->postJson("/api/v1/classes/{$this->academicClass->id}/grades/submit");
        $submitRes->assertStatus(200);

        // 5. Academic Admin finalizes grades
        \Laravel\Sanctum\Sanctum::actingAs($this->admin);
        $finalRes = $this->postJson("/api/v1/classes/{$this->academicClass->id}/grades/finalize");
        $finalRes->assertStatus(200);

        // 6. Student views own grade and verifies 85.00 / A / 4.00
        \Laravel\Sanctum\Sanctum::actingAs($this->studentUser);
        $studentRes = $this->getJson("/api/v1/classes/{$this->academicClass->id}/grades/{$this->student->id}");
        $studentRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'assessment' => [
                        'final_score' => 85.00,
                        'letter_grade' => 'A',
                        'grade_point' => 4.00,
                    ],
                ],
            ]);
    }
}
