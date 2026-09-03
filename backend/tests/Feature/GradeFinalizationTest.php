<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Tests\TestCase;

class GradeFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected AcademicClass $academicClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->academicClass = AcademicClass::first();
    }

    public function test_can_finalize_class_grades_transaction_safely(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/classes/{$this->academicClass->id}/grades/finalize");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'class',
                    'scheme',
                    'summary',
                    'grades',
                ],
            ]);

        $this->assertEquals(0, StudentGrade::where('academic_class_id', $this->academicClass->id)
            ->where('status', '!=', GradeStatus::FINAL->value)
            ->count()
        );
    }
}
