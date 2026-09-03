<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Models\AuditLog;
use Modules\Identity\Models\User;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $dosenToken;
    protected string $studentToken;
    protected User $admin;
    protected StudyProgram $studyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $dosen = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $dosen->createToken('dosen_token')->plainTextToken;

        $mahasiswa = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $mahasiswa->createToken('student_token')->plainTextToken;

        $this->studyProgram = StudyProgram::first();
    }

    public function test_can_list_and_filter_students(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/students?search=Budi&status=active');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'student_number', 'full_name', 'status', 'study_program'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_create_student_with_family_and_education(): void
    {
        $payload = [
            'study_program_id' => $this->studyProgram->id,
            'student_number' => '202601999',
            'national_student_number' => '0098765432',
            'national_id' => '3201019999990001',
            'full_name' => 'Muhammad Rifqi',
            'gender' => Gender::MALE->value,
            'birth_place' => 'Jakarta',
            'birth_date' => '2006-05-10',
            'religion' => 'Islam',
            'email' => 'rifqi@example.com',
            'phone' => '081234567899',
            'admission_year' => 2026,
            'families' => [
                [
                    'relationship' => 'father',
                    'full_name' => 'Ahmad Rifqi',
                    'phone' => '081234567800',
                    'occupation' => 'Wiraswasta',
                ],
            ],
            'educations' => [
                [
                    'institution_name' => 'SMA Negeri 1 Jakarta',
                    'level' => 'SMA',
                    'major' => 'IPA',
                    'graduation_year' => 2026,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/students', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_number' => '202601999',
                    'full_name' => 'Muhammad Rifqi',
                ],
            ]);

        $this->assertDatabaseHas('students', ['student_number' => '202601999']);
        $this->assertDatabaseHas('student_families', ['full_name' => 'Ahmad Rifqi']);
        $this->assertDatabaseHas('student_educations', ['institution_name' => 'SMA Negeri 1 Jakarta']);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Student',
            'action' => 'created',
        ]);
    }

    public function test_rejects_duplicate_student_number(): void
    {
        $payload = [
            'study_program_id' => $this->studyProgram->id,
            'student_number' => '202501001', // Already exists in seeder
            'full_name' => 'Duplicate Student',
            'gender' => Gender::MALE->value,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/students', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonStructure([
                'errors' => ['student_number'],
            ]);
    }

    public function test_can_change_student_status_with_audit(): void
    {
        $student = Student::where('student_number', '202501001')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/students/{$student->id}/status", [
                'status' => StudentStatus::LEAVE->value,
                'notes' => 'Cuti akademik semester genap karena alasan kesehatan.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'leave',
                ],
            ]);

        $this->assertEquals(StudentStatus::LEAVE, $student->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Student',
            'action' => 'status_changed',
            'entity_id' => $student->id,
        ]);
    }

    public function test_student_cannot_create_or_delete_other_students(): void
    {
        $student = Student::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->deleteJson("/api/v1/students/{$student->id}");

        $response->assertStatus(403);
    }
}
