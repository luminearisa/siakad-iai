<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Enums\Gender;
use Tests\TestCase;

class LecturerAccountTest extends TestCase
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

    /**
     * Create a lecturer record without a linked portal account.
     *
     * Note: the create action auto-provisions a user only when an email is
     * supplied, so omitting the email is what produces the "Belum Punya Akun"
     * state that the detail page shows.
     */
    protected function createLecturerWithoutAccount(array $overrides = []): Lecturer
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/lecturers', array_merge([
                'homebase_study_program_id' => $this->studyProgram->id,
                'nidn' => '0099001100',
                'lecturer_number' => 'DOS-TEST-001',
                'full_name' => 'Dr. Tanpa Akun, M.Pd',
                'gender' => Gender::MALE->value,
                'status' => LecturerStatus::ACTIVE->value,
            ], $overrides));

        $response->assertStatus(201);

        return Lecturer::findOrFail($response->json('data.id'));
    }

    public function test_admin_can_create_portal_account_for_lecturer(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();
        $this->assertNull($lecturer->user_id);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/create-account", [
                'email' => 'dosen.baru@siakad.ac.id',
                'password' => 'rahasia123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Akun portal dosen berhasil dibuat.',
            ]);

        $lecturer->refresh();
        $this->assertNotNull($lecturer->user_id);
        $this->assertSame($lecturer->user_id, $response->json('data.user_id'));

        $user = $lecturer->user;
        $this->assertSame('dosen.baru@siakad.ac.id', $user->email);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertTrue($user->hasRole('dosen'));

        // The response must expose the linked account so the UI can react immediately.
        $this->assertSame($user->email, $response->json('data.user.email'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Lecturer',
            'action' => 'created',
        ]);
    }

    public function test_rejects_creating_account_when_lecturer_already_has_one(): void
    {
        $lecturer = Lecturer::where('nidn', '0011223301')->first();
        $this->assertNotNull($lecturer->user_id);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/create-account", [
                'email' => 'duplikat@siakad.ac.id',
                'password' => 'rahasia123',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('users', ['email' => 'duplikat@siakad.ac.id']);
    }

    public function test_rejects_create_account_with_duplicate_email(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/create-account", [
                'email' => 'admin@siakad.ac.id',
                'password' => 'rahasia123',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_reset_password_provisions_account_when_missing(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();
        $this->assertNull($lecturer->user_id);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/reset-password", [
                'password' => 'passwordbaru1',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $lecturer->refresh();
        $this->assertNotNull($lecturer->user_id);
        $this->assertSame('dostest001@dosen.ac.id', $lecturer->user->email);
        $this->assertTrue(Hash::check('passwordbaru1', $lecturer->user->password));
        $this->assertTrue($lecturer->user->hasRole('dosen'));
    }

    public function test_reset_password_updates_existing_account(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/create-account", [
                'email' => 'reset.target@siakad.ac.id',
                'password' => 'passwordlama',
            ])->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/reset-password", [
                'password' => 'passwordbaru2',
            ]);

        $response->assertStatus(200);

        $user = $lecturer->fresh()->user;
        $this->assertTrue(Hash::check('passwordbaru2', $user->password));
        $this->assertFalse(Hash::check('passwordlama', $user->password));
        $this->assertSame('reset.target@siakad.ac.id', $user->email);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Lecturer',
            'action' => 'updated',
        ]);
    }

    public function test_rejects_reset_password_shorter_than_six_characters(): void
    {
        $lecturer = Lecturer::where('nidn', '0011223301')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/reset-password", [
                'password' => '123',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_admin_can_toggle_lecturer_account_status(): void
    {
        $lecturer = Lecturer::where('nidn', '0011223301')->first();
        $this->assertSame(UserStatus::ACTIVE, $lecturer->user->status);

        $deactivate = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/lecturers/{$lecturer->id}/toggle-account-status");

        $deactivate->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['user' => ['status' => 'inactive']],
            ]);

        $this->assertSame(UserStatus::INACTIVE, $lecturer->fresh()->user->status);

        $activate = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/lecturers/{$lecturer->id}/toggle-account-status");

        $activate->assertStatus(200);
        $this->assertSame(UserStatus::ACTIVE, $lecturer->fresh()->user->status);
    }

    public function test_rejects_toggle_account_status_when_lecturer_has_no_account(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/lecturers/{$lecturer->id}/toggle-account-status");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_student_cannot_manage_lecturer_accounts(): void
    {
        $lecturer = $this->createLecturerWithoutAccount();

        // Sanctum caches the resolved user on the guard between requests inside a
        // single test, so the admin session must be forgotten before acting as
        // the student (same pattern used by EnrollmentTest).
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/create-account", [
                'email' => 'nakal@siakad.ac.id',
                'password' => 'rahasia123',
            ])->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/lecturers/{$lecturer->id}/reset-password", [
                'password' => 'rahasia123',
            ])->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->patchJson("/api/v1/lecturers/{$lecturer->id}/toggle-account-status")
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'nakal@siakad.ac.id']);
    }
}
