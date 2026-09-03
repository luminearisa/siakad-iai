<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Graduation\Models\YudisiumParticipant;
use Modules\Graduation\Models\YudisiumPeriod;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class YudisiumTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected YudisiumPeriod $period;
    protected Student $student;
    protected StudyProgram $studyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->studyProgram = StudyProgram::first();
        $semester = Semester::first();
        $this->student = Student::first();

        $this->period = YudisiumPeriod::create([
            'semester_id' => $semester?->id,
            'name' => 'Yudisium Periode Uji',
            'registration_start_date' => '2026-07-01',
            'registration_end_date' => '2026-07-31',
            'yudisium_date' => '2026-08-15',
            'is_active' => true,
        ]);
    }

    public function test_can_list_yudisium_periods(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/graduation/yudisium/periods');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_audit_and_list_eligible_students(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/graduation/yudisium/eligible');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_register_eligible_students(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/graduation/yudisium/eligible/register', [
                'yudisium_period_id' => $this->period->id,
                'student_ids' => [$this->student->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('yudisium_participants', [
            'yudisium_period_id' => $this->period->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_can_list_approvals_with_stats(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/graduation/yudisium/approvals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'stats' => [
                        'ready',
                        'needs_revision',
                        'submitted',
                        're_review',
                        'under_review',
                    ],
                ],
            ]);
    }

    public function test_can_input_sk_yudisium_batch(): void
    {
        $participant = YudisiumParticipant::create([
            'yudisium_period_id' => $this->period->id,
            'student_id' => $this->student->id,
            'status' => 'ready',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/graduation/yudisium/participants/input-sk-batch', [
                'yudisium_period_id' => $this->period->id,
                'study_program_id' => $this->student->study_program_id,
                'sk_number' => 'SK-YUD/2026/001',
                'sk_date' => '2026-08-26',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('yudisium_participants', [
            'id' => $participant->id,
            'sk_number' => 'SK-YUD/2026/001',
            'status' => 'passed',
        ]);
    }
}
