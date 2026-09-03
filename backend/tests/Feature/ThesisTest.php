<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;
use Modules\Thesis\Models\Thesis;
use Tests\TestCase;

class ThesisTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected Student $student;
    protected Lecturer $lecturer;
    protected Semester $semester;
    protected StudyProgram $studyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->studyProgram = StudyProgram::first();
        $this->semester = Semester::first();
        $this->student = Student::first();
        $this->lecturer = Lecturer::first();
    }

    public function test_can_list_theses_and_stats(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/theses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'stats' => [
                        'completed',
                        'active',
                        'inactive',
                        'pending_approval',
                    ],
                ],
            ]);
    }

    public function test_can_create_thesis(): void
    {
        $payload = [
            'student_id' => $this->student->id,
            'start_semester_id' => $this->semester->id,
            'start_date' => '2026-08-26',
            'submission_date' => '2026-08-26',
            'status' => 'active',
            'title_id' => 'Rancang Bangun Sistem Informasi Akademik Berbasis Web',
            'title_en' => 'Design and Development of Web-Based Academic Information System',
            'topic_id' => 'Sistem Informasi',
            'topic_en' => 'Information System',
            'supervisor_ids' => [$this->lecturer->id],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/theses', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title_id', 'Rancang Bangun Sistem Informasi Akademik Berbasis Web');

        $this->assertDatabaseHas('theses', [
            'student_id' => $this->student->id,
            'title_id' => 'Rancang Bangun Sistem Informasi Akademik Berbasis Web',
        ]);

        $this->assertDatabaseHas('thesis_supervisors', [
            'lecturer_id' => $this->lecturer->id,
            'role' => 'primary',
        ]);
    }
}
