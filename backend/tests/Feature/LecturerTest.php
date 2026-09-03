<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Enums\Gender;
use Tests\TestCase;

class LecturerTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected User $admin;
    protected StudyProgram $studyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $mahasiswa = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $mahasiswa->createToken('student_token')->plainTextToken;

        $this->studyProgram = StudyProgram::first();
    }

    public function test_can_list_and_filter_lecturers(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/lecturers?search=Ahmad&status=active');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'nidn', 'full_name', 'status', 'homebase_study_program'],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_create_lecturer_with_education_and_expertise(): void
    {
        $payload = [
            'homebase_study_program_id' => $this->studyProgram->id,
            'nidn' => '0099887766',
            'nip' => '198801012015011005',
            'full_name' => 'Dr. Hasan Basri, M.Pd',
            'gender' => Gender::MALE->value,
            'academic_degree' => 'Dr., M.Pd',
            'functional_position' => 'Lektor',
            'email' => 'hasan.basri@siakad.ac.id',
            'phone' => '081234567801',
            'status' => LecturerStatus::ACTIVE->value,
            'educations' => [
                [
                    'degree' => 'S1',
                    'institution_name' => 'UIN Sunan Kalijaga',
                    'major' => 'Pendidikan Islam',
                    'graduation_year' => 2010,
                ],
                [
                    'degree' => 'S2',
                    'institution_name' => 'Universitas Negeri Yogyakarta',
                    'major' => 'Manajemen Pendidikan',
                    'graduation_year' => 2013,
                ],
            ],
            'expertises' => [
                [
                    'name' => 'Evaluasi Pendidikan',
                    'description' => 'Pengembangan instrumen asesmen pembelajaran',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/lecturers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nidn' => '0099887766',
                    'full_name' => 'Dr. Hasan Basri, M.Pd',
                ],
            ]);

        $this->assertDatabaseHas('lecturers', ['nidn' => '0099887766']);
        $this->assertDatabaseHas('lecturer_educations', ['institution_name' => 'UIN Sunan Kalijaga']);
        $this->assertDatabaseHas('lecturer_expertises', ['name' => 'Evaluasi Pendidikan']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Lecturer',
            'action' => 'created',
        ]);
    }

    public function test_rejects_duplicate_nidn(): void
    {
        $payload = [
            'nidn' => '0011223301', // Already exists in seeder
            'full_name' => 'Duplicate Lecturer',
            'gender' => Gender::MALE->value,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/lecturers', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['nidn'],
            ]);
    }

    public function test_can_change_lecturer_status(): void
    {
        $lecturer = Lecturer::where('nidn', '0011223301')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/lecturers/{$lecturer->id}/status", [
                'status' => LecturerStatus::INACTIVE->value,
                'notes' => 'Tugas belajar program doktoral di luar negeri.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'inactive',
                ],
            ]);

        $this->assertEquals(LecturerStatus::INACTIVE, $lecturer->fresh()->status);
    }
}
