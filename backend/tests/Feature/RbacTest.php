<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasPermissionTo('students.view'));
        $this->assertTrue($admin->hasPermissionTo('non_existent.permission'));
    }

    public function test_roles_and_permissions_are_properly_checked(): void
    {
        $dosen = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->assertTrue($dosen->hasRole('dosen'));
        $this->assertTrue($dosen->hasPermissionTo('grades.create'));
        $this->assertFalse($dosen->hasPermissionTo('settings.manage'));
    }

    public function test_super_admin_can_assign_roles_to_user(): void
    {
        $admin = User::where('email', 'admin@siakad.ac.id')->first();
        $student = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/users/{$student->id}/roles", [
                'roles' => ['dosen', 'mahasiswa'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Roles assigned successfully.',
            ]);

        $this->assertTrue($student->fresh()->hasRole('dosen'));
        $this->assertTrue($student->fresh()->hasRole('mahasiswa'));
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $student = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $token = $student->createToken('student_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/audit/logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_create_custom_role_with_permissions(): void
    {
        $admin = User::where('email', 'admin@siakad.ac.id')->first();
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/roles', [
                'name' => 'kepala_perpustakaan',
                'display_name' => 'Kepala Perpustakaan',
                'description' => 'Manages library resources',
                'permissions' => ['students.view', 'courses.view'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'kepala_perpustakaan',
                ],
            ]);

        $role = Role::where('name', 'kepala_perpustakaan')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('students.view'));
    }
}
