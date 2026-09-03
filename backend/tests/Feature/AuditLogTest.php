<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Audit\Models\AuditLog;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->token = $this->admin->createToken('admin_token')->plainTextToken;
    }

    public function test_creating_auditable_model_generates_audit_log(): void
    {
        $institution = Institution::first();

        // Perform faculty creation through API
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/faculties', [
                'institution_id' => $institution->id,
                'code' => 'FPSI',
                'name' => 'Fakultas Psikologi',
                'status' => 'active',
            ]);

        $auditLog = AuditLog::where('action', 'created')
            ->where('entity_type', Faculty::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('Academic', $auditLog->module);
        $this->assertEquals($this->admin->id, $auditLog->user_id);
    }

    public function test_updating_auditable_model_generates_audit_log_with_changes(): void
    {
        $faculty = Faculty::first();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/academic/faculties/{$faculty->id}", [
                'institution_id' => $faculty->institution_id,
                'code' => $faculty->code,
                'name' => 'Updated Faculty Name',
                'status' => 'active',
            ]);

        $auditLog = AuditLog::where('action', 'updated')
            ->where('entity_type', Faculty::class)
            ->where('entity_id', $faculty->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('Updated Faculty Name', $auditLog->new_values['name']);
    }

    public function test_can_retrieve_audit_logs_via_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/audit/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'action', 'module', 'created_at'],
                ],
                'meta',
            ]);
    }
}
