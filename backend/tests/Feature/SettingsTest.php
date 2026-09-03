<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingService;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;
    protected SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->token = $this->admin->createToken('admin_token')->plainTextToken;
        $this->service = app(SettingService::class);
    }

    public function test_can_read_typed_settings(): void
    {
        $maxSks = $this->service->get('max_sks');
        $this->assertIsInt($maxSks);
        $this->assertEquals(24, $maxSks);

        $gradingScale = $this->service->get('default_grading_scale');
        $this->assertIsArray($gradingScale);
        $this->assertArrayHasKey('A', $gradingScale);
    }

    public function test_can_update_setting_via_api(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v1/settings/max_sks', [
                'value' => '20',
                'type' => 'integer',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'max_sks',
                    'value' => 20,
                ],
            ]);

        $this->assertEquals(20, $this->service->get('max_sks'));
    }

    public function test_can_batch_update_settings(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v1/settings/batch', [
                'settings' => [
                    'institution_name' => 'IAI Baru Updated',
                    'max_sks' => '22',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Settings batch updated successfully.',
            ]);

        $this->assertEquals('IAI Baru Updated', $this->service->get('institution_name'));
        $this->assertEquals(22, $this->service->get('max_sks'));
    }
}
