<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\StudentGrade;
use Modules\Identity\Models\User;
use Tests\TestCase;

class GradeRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected StudentGrade $finalGrade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->finalGrade = StudentGrade::first();
        $this->finalGrade->update(['status' => GradeStatus::FINAL, 'score' => 80.00]);
    }

    public function test_can_formally_revise_final_grade_and_create_audit_record(): void
    {
        $payload = [
            'new_score' => 92.50,
            'reason' => 'Perbaikan penilaian setelah sanggah nilai dan verifikasi lembar ujian mahasiswa.',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/student-grades/{$this->finalGrade->id}/revise", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'score' => 92.50,
                ],
            ]);

        $this->assertDatabaseHas('grade_revisions', [
            'student_grade_id' => $this->finalGrade->id,
            'old_score' => 80.00,
            'new_score' => 92.50,
            'reason' => $payload['reason'],
        ]);
    }

    public function test_can_view_grade_revision_history(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/student-grades/{$this->finalGrade->id}/revisions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}
