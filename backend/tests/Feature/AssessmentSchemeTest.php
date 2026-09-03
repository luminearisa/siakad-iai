<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AssessmentSchemeTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $dosenToken;
    protected User $admin;
    protected User $dosenUser;
    protected AcademicClass $academicClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->academicClass = AcademicClass::first();
    }

    public function test_can_create_assessment_scheme_for_class(): void
    {
        $components = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->take(2)->get();

        $payload = [
            'name' => 'Skema Penilaian Baru Gasal',
            'description' => 'Skema khusus kelas A',
            'items' => [
                ['assessment_component_id' => $components[0]->id, 'weight' => 40.00],
                ['assessment_component_id' => $components[1]->id, 'weight' => 60.00],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/assessment-scheme", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Skema Penilaian Baru Gasal',
                    'total_weight' => 100.00,
                    'status' => 'draft',
                ],
            ]);

        $this->assertDatabaseHas('assessment_schemes', [
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Skema Penilaian Baru Gasal',
            'total_weight' => 100.00,
        ]);
    }

    public function test_can_update_draft_assessment_scheme(): void
    {
        $scheme = AssessmentScheme::where('academic_class_id', $this->academicClass->id)->first();
        $scheme->update(['status' => SchemeStatus::DRAFT, 'is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->putJson("/api/v1/assessment-schemes/{$scheme->id}", [
                'name' => 'Skema Penilaian Diperbarui',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Skema Penilaian Diperbarui',
                ],
            ]);
    }

    public function test_can_activate_assessment_scheme_with_full_weight(): void
    {
        $scheme = AssessmentScheme::where('academic_class_id', $this->academicClass->id)->first();
        $scheme->recalculateTotalWeight();

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/assessment-schemes/{$scheme->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => true,
                    'status' => 'active',
                ],
            ]);
    }

    public function test_reject_activation_if_total_weight_not_100(): void
    {
        $scheme = AssessmentScheme::create([
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Skema Belum Lengkap',
            'status' => SchemeStatus::DRAFT,
            'total_weight' => 50.00,
            'is_active' => false,
        ]);

        $component = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->first();
        $scheme->items()->create([
            'assessment_component_id' => $component->id,
            'weight' => 50.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/assessment-schemes/{$scheme->id}/activate");

        $response->assertStatus(422);
    }

    public function test_prevent_multiple_active_schemes_per_class(): void
    {
        $components = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->take(2)->get();

        // Scheme 1 active
        $scheme1 = AssessmentScheme::first();
        $scheme1->update(['status' => SchemeStatus::ACTIVE, 'is_active' => true, 'total_weight' => 100.00]);

        // Scheme 2 activated
        $scheme2 = AssessmentScheme::create([
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Skema Kedua 100%',
            'status' => SchemeStatus::DRAFT,
            'total_weight' => 100.00,
            'is_active' => false,
        ]);
        $scheme2->items()->createMany([
            ['assessment_component_id' => $components[0]->id, 'weight' => 50.00],
            ['assessment_component_id' => $components[1]->id, 'weight' => 50.00],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/assessment-schemes/{$scheme2->id}/activate");

        $response->assertStatus(200);

        // Scheme 1 should now be archived and inactive
        $scheme1->refresh();
        $this->assertFalse($scheme1->is_active);
        $this->assertEquals(SchemeStatus::ARCHIVED, $scheme1->status);

        // Scheme 2 should be active
        $scheme2->refresh();
        $this->assertTrue($scheme2->is_active);
    }
}
