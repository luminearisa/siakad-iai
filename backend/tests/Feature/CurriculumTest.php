<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\StudyProgram;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;
use Modules\Identity\Models\User;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected StudyProgram $studyProgram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->studyProgram = StudyProgram::where('code', 'PAI')->first();
    }

    public function test_can_list_and_filter_curricula(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/curricula?study_program_id=' . $this->studyProgram->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'name', 'status', 'total_credits'],
                ],
                'meta',
            ]);
    }

    public function test_can_create_curriculum_and_auto_generate_semesters(): void
    {
        $payload = [
            'study_program_id' => $this->studyProgram->id,
            'code' => 'KUR-PAI-2030',
            'name' => 'Kurikulum PAI 2030',
            'version' => '2030.1',
            'start_year' => 2030,
            'end_year' => 2035,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/curricula', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'KUR-PAI-2030',
                    'status' => 'draft',
                ],
            ]);

        $curriculum = Curriculum::where('code', 'KUR-PAI-2030')->first();
        $this->assertNotNull($curriculum);
        $this->assertCount(8, $curriculum->semesters);
    }

    public function test_can_add_subject_to_curriculum_semester(): void
    {
        $curriculum = Curriculum::where('code', 'KUR-PAI-2026')->first();
        $semester = $curriculum->semesters()->where('semester_number', 4)->first();
        $course = Course::where('code', 'HKI-201')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/curriculum-semesters/{$semester->id}/subjects", [
                'course_id' => $course->id,
                'is_mandatory' => true,
                'credits_override' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'course_id' => $course->id,
                    'is_mandatory' => true,
                ],
            ]);

        $this->assertDatabaseHas('curriculum_subjects', [
            'curriculum_semester_id' => $semester->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_rejects_duplicate_subject_in_same_semester(): void
    {
        $curriculum = Curriculum::where('code', 'KUR-PAI-2026')->first();
        $sem1 = $curriculum->semesters()->where('semester_number', 1)->first();
        $existingCourse = Course::where('code', 'MKU-101')->first(); // Already in sem 1 from seeder

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/curriculum-semesters/{$sem1->id}/subjects", [
                'course_id' => $existingCourse->id,
                'is_mandatory' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['course_id'],
            ]);
    }

    public function test_can_activate_curriculum(): void
    {
        $newCurriculum = Curriculum::create([
            'study_program_id' => $this->studyProgram->id,
            'code' => 'KUR-PAI-TEST-ACTIVATE',
            'name' => 'Kurikulum Test Activate',
            'version' => '1.0',
            'status' => CurriculumStatus::DRAFT,
        ]);

        $sem = $newCurriculum->semesters()->create([
            'semester_number' => 1,
            'name' => 'Semester 1',
        ]);

        $course = Course::first();
        $sem->subjects()->create([
            'course_id' => $course->id,
            'is_mandatory' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/curricula/{$newCurriculum->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'active',
                ],
            ]);

        $this->assertEquals(CurriculumStatus::ACTIVE, $newCurriculum->fresh()->status);
    }
}
