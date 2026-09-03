<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class GradeValidationTest extends TestCase
{
    use RefreshDatabase;

    protected string $dosenToken;
    protected User $dosenUser;
    protected AcademicClass $academicClass;
    protected Student $student;
    protected AssessmentComponent $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->academicClass = AcademicClass::first();
        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->component = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->first();
    }

    public function test_reject_negative_score(): void
    {
        $payload = [
            'grades' => [
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $this->component->id,
                    'score' => -15.00,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades", $payload);

        $response->assertStatus(422);
    }

    public function test_reject_score_greater_than_max_score(): void
    {
        $payload = [
            'grades' => [
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $this->component->id,
                    'score' => 150.00, // max is 100
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades", $payload);

        $response->assertStatus(422);
    }

    public function test_reject_grading_on_cancelled_class(): void
    {
        $this->academicClass->update(['status' => ClassStatus::CANCELLED]);

        $payload = [
            'grades' => [
                [
                    'student_id' => $this->student->id,
                    'assessment_component_id' => $this->component->id,
                    'score' => 85.00,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades", $payload);

        $response->assertStatus(422);
    }
}
