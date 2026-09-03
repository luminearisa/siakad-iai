<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->token = $this->admin->createToken('test_token')->plainTextToken;
    }

    public function test_can_list_and_filter_institutions(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/academic/institutions?search=IAI');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'code', 'status'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_institution(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/institutions', [
                'name' => 'Institut Baru',
                'short_name' => 'IB',
                'code' => 'IB-002',
                'address' => 'Jl. Baru No. 10',
                'phone' => '08123456789',
                'website' => 'https://ib.ac.id',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'IB-002',
                ],
            ]);

        $this->assertDatabaseHas('institutions', ['code' => 'IB-002']);
    }

    public function test_can_create_faculty_linked_to_institution(): void
    {
        $institution = Institution::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/faculties', [
                'institution_id' => $institution->id,
                'code' => 'FK',
                'name' => 'Fakultas Kedokteran',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'FK',
                    'institution_id' => $institution->id,
                ],
            ]);
    }

    public function test_can_create_study_program_linked_to_faculty(): void
    {
        $faculty = Faculty::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/study-programs', [
                'faculty_id' => $faculty->id,
                'code' => 'IF',
                'name' => 'Informatika',
                'degree' => 'S1',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'IF',
                    'degree' => 'S1',
                ],
            ]);
    }

    public function test_academic_year_and_semesters_relationship(): void
    {
        $academicYear = AcademicYear::where('name', '2025/2026')->first();
        $this->assertNotNull($academicYear);
        $this->assertCount(2, $academicYear->semesters);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/academic/academic-years/{$academicYear->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $academicYear->id,
                    'name' => '2025/2026',
                ],
            ]);
    }
}
