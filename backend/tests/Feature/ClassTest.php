<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Tests\TestCase;

class ClassTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected User $admin;
    protected Semester $semester;
    protected Course $course;
    protected StudyProgram $studyProgram;
    protected Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $student = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $student->createToken('student_token')->plainTextToken;

        $this->semester = Semester::first();
        $this->course = Course::first();
        $this->studyProgram = StudyProgram::first();
        $this->lecturer = Lecturer::first();
    }

    public function test_can_list_and_filter_classes(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/classes?semester_id={$this->semester->id}&status=open");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'name', 'section', 'capacity', 'status'],
                ],
                'meta',
            ])
            ->assertJson(['success' => true]);
    }

    public function test_can_create_class_with_lecturers(): void
    {
        $payload = [
            'semester_id' => $this->semester->id,
            'course_id' => $this->course->id,
            'study_program_id' => $this->studyProgram->id,
            'code' => 'TEST-CLASS-B',
            'section' => 'B',
            'capacity' => 30,
            'status' => ClassStatus::DRAFT->value,
            'lecturers' => [
                [
                    'lecturer_id' => $this->lecturer->id,
                    'role' => 'primary',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/classes', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'TEST-CLASS-B',
                    'section' => 'B',
                    'status' => 'draft',
                ],
            ]);

        $this->assertDatabaseHas('academic_classes', ['code' => 'TEST-CLASS-B']);
        $this->assertDatabaseHas('class_lecturers', ['lecturer_id' => $this->lecturer->id]);
    }

    public function test_rejects_duplicate_class_section_in_same_semester(): void
    {
        $payload = [
            'semester_id' => $this->semester->id,
            'course_id' => $this->course->id,
            'code' => 'DUPLICATE-A',
            'section' => 'A', // Already exists in seeder for this course & semester
            'capacity' => 30,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/classes', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['section']]);
    }

    public function test_can_transition_class_status(): void
    {
        $class = AcademicClass::where('code', 'PAI201-A')->first();

        // Close class
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/classes/{$class->id}/close");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['status' => 'closed']]);

        // Open class
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/classes/{$class->id}/open");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['status' => 'open']]);
    }

    public function test_student_cannot_delete_class(): void
    {
        $class = AcademicClass::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->deleteJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(403);
    }
}
