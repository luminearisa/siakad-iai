<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AssessmentComponentTest extends TestCase
{
    use RefreshDatabase;

    protected string $dosenToken;
    protected User $dosenUser;
    protected AcademicClass $academicClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->academicClass = AcademicClass::first();
    }

    public function test_can_create_assessment_component(): void
    {
        $payload = [
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Tugas Portofolio Akhir',
            'code' => 'PORTOFOLIO',
            'type' => 'project',
            'max_score' => 100.00,
            'is_required' => true,
            'sequence' => 6,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson('/api/v1/assessment-components', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Tugas Portofolio Akhir',
                    'code' => 'PORTOFOLIO',
                    'type' => 'project',
                ],
            ]);
    }

    public function test_cannot_create_duplicate_component_code_in_same_class(): void
    {
        $payload = [
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Tugas Tambahan',
            'code' => 'TUGAS', // already exists in class
            'type' => 'assignment',
            'max_score' => 100.00,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson('/api/v1/assessment-components', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_update_component(): void
    {
        $component = AssessmentComponent::where('academic_class_id', $this->academicClass->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->putJson("/api/v1/assessment-components/{$component->id}", [
                'name' => 'Tugas Mandiri Revisi',
                'code' => $component->code,
                'type' => $component->type->value,
                'max_score' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Tugas Mandiri Revisi',
                ],
            ]);
    }

    public function test_can_delete_component(): void
    {
        $component = AssessmentComponent::create([
            'academic_class_id' => $this->academicClass->id,
            'name' => 'Quiz Tambahan',
            'code' => 'QUIZ_EXTRA',
            'type' => 'quiz',
            'max_score' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->deleteJson("/api/v1/assessment-components/{$component->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('assessment_components', ['id' => $component->id]);
    }
}
