<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class GradeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected string $studentToken;
    protected User $studentUser;
    protected Student $student;
    protected AcademicClass $academicClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $this->studentUser->createToken('student_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->student->user_id = $this->studentUser->id;
        $this->student->save();

        $this->academicClass = AcademicClass::first();
    }

    public function test_student_can_view_own_grades(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson("/api/v1/classes/{$this->academicClass->id}/grades/{$this->student->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student' => [
                        'id' => $this->student->id,
                    ],
                ],
            ]);
    }

    public function test_student_cannot_input_or_modify_grades(): void
    {
        $grade = StudentGrade::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->putJson("/api/v1/student-grades/{$grade->id}", [
                'student_id' => $this->student->id,
                'assessment_component_id' => $grade->assessment_component_id,
                'score' => 99.00,
            ]);

        $response->assertStatus(403);
    }
}
