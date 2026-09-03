<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class GradeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $dosenToken;
    protected User $admin;
    protected User $dosenUser;
    protected AcademicClass $academicClass;
    protected Student $student;
    protected AssessmentComponent $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->academicClass = AcademicClass::first();
        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->component = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->first();

        // Ensure student has valid enrollment in class
        $enrollment = \Modules\Enrollment\Models\StudentEnrollment::firstOrCreate([
            'student_id' => $this->student->id,
            'semester_id' => $this->academicClass->semester_id,
        ], [
            'status' => 'approved',
            'academic_year_id' => $this->academicClass->semester?->academic_year_id ?: 1,
            'total_credits' => 3,
        ]);

        \Modules\Enrollment\Models\StudentEnrollmentItem::firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'class_id' => $this->academicClass->id,
        ], [
            'course_id' => $this->academicClass->course_id,
            'credits' => 3,
            'status' => 'enrolled',
        ]);
    }

    public function test_input_draft_grade_and_submit(): void
    {
        // Reset any existing seeded grades for this student and class
        StudentGrade::where('academic_class_id', $this->academicClass->id)
            ->where('student_id', $this->student->id)
            ->delete();

        // 1. Input draft grade
        $payload = [
            'grades' => [
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $this->component->id,
                    'score' => 88.00,
                    'notes' => 'Draft nilai kuis',
                ],
            ],
        ];

        $inputRes = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades", $payload);

        $inputRes->assertStatus(200);

        // 2. Submit class grades
        $submitRes = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades/submit");

        $submitRes->assertStatus(200);

        $this->assertDatabaseHas('student_grades', [
            'academic_class_id' => $this->academicClass->id,
            'student_id' => $this->student->id,
            'status' => GradeStatus::SUBMITTED->value,
        ]);
    }

    public function test_admin_can_request_revision_on_submitted_grade(): void
    {
        $grade = StudentGrade::first();
        $grade->update(['status' => GradeStatus::SUBMITTED]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/student-grades/{$grade->id}/request-revision", [
                'reason' => 'Mohon periksa kembali skor tugas mahasiswa ini.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'revision_required',
                ],
            ]);
    }
}
