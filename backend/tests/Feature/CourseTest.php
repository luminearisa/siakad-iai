<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Course\Enums\CourseType;
use Modules\Course\Models\Course;
use Modules\Identity\Models\User;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;
    }

    public function test_can_list_and_filter_courses(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/courses?search=Arab');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'name', 'credits', 'prerequisites'],
                ],
                'meta',
            ]);
    }

    public function test_can_create_course_with_prerequisites(): void
    {
        $prereq = Course::where('code', 'PAI-201')->first();

        $payload = [
            'code' => 'PAI-401',
            'name' => 'Filsafat Pendidikan Islam Lanjutan',
            'short_name' => 'Filsafat PAI 2',
            'description' => 'Kajian epistemologi pendidikan islam',
            'credits' => 3,
            'theory_credits' => 3,
            'practical_credits' => 0,
            'type' => CourseType::THEORY->value,
            'category' => 'program',
            'prerequisites' => [
                [
                    'course_id' => $prereq->id,
                    'minimum_grade' => 'C',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/courses', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'PAI-401',
                    'name' => 'Filsafat Pendidikan Islam Lanjutan',
                ],
            ]);

        $this->assertDatabaseHas('courses', ['code' => 'PAI-401']);
        $this->assertDatabaseHas('course_prerequisites', [
            'course_id' => Course::where('code', 'PAI-401')->first()->id,
            'prerequisite_course_id' => $prereq->id,
        ]);
    }

    public function test_can_update_course_prerequisites(): void
    {
        $course = Course::where('code', 'PAI-301')->first();
        $newPrereq = Course::where('code', 'PAI-201')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/courses/{$course->id}/prerequisites", [
                'prerequisites' => [
                    [
                        'course_id' => $newPrereq->id,
                        'minimum_grade' => 'B',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Course prerequisites updated successfully.',
            ]);

        $this->assertTrue($course->fresh()->prerequisites->contains('id', $newPrereq->id));
    }
}
