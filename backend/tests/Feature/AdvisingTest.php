<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AdvisingTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected User $admin;
    protected Student $student;
    protected Lecturer $lecturer1;
    protected Lecturer $lecturer2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first();
        $this->lecturer1 = Lecturer::where('nidn', '0011223301')->first();
        $this->lecturer2 = Lecturer::where('nidn', '0011223302')->first();
    }

    public function test_can_get_current_student_advisor(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/students/{$this->student->id}/advisor");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'lecturer_id' => $this->lecturer1->id,
                    'status' => 'active',
                ],
            ]);
    }

    public function test_can_change_academic_advisor_and_transition_old_assignment(): void
    {
        $payload = [
            'new_lecturer_id' => $this->lecturer2->id,
            'change_date' => now()->format('Y-m-d'),
            'reason' => 'Pergantian pembimbing karena dosen sebelumnya menjabat struktural.',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/students/{$this->student->id}/advisor", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'lecturer_id' => $this->lecturer2->id,
                    'status' => 'active',
                ],
            ]);

        // Old advisor should be transitioned to 'transferred'
        $oldAdvisor = AcademicAdvisor::where('student_id', $this->student->id)
            ->where('lecturer_id', $this->lecturer1->id)
            ->first();

        $this->assertEquals(AdvisorStatus::TRANSFERRED, $oldAdvisor->status);
    }

    public function test_rejects_inactive_lecturer_as_advisor(): void
    {
        $inactiveLecturer = Lecturer::where('nidn', '0011223303')->first();
        $inactiveLecturer->update(['status' => LecturerStatus::INACTIVE]);

        $payload = [
            'student_id' => $this->student->id,
            'lecturer_id' => $inactiveLecturer->id,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/advisors', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lecturer_id']]);
    }

    public function test_can_create_advising_session(): void
    {
        $payload = [
            'student_id' => $this->student->id,
            'lecturer_id' => $this->lecturer1->id,
            'session_date' => now()->format('Y-m-d'),
            'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
            'notes' => 'Mahasiswa diarahkan mengambil 20 SKS dan fokus pada pemahaman konsep dasar pedagogik.',
            'status' => 'completed',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/advising-sessions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('advising_sessions', [
            'student_id' => $this->student->id,
            'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
        ]);
    }
}
