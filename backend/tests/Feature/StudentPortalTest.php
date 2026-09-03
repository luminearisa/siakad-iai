<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected string $studentToken;
    protected User $studentUser;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $this->studentUser->createToken('student_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->student->user_id = $this->studentUser->id;
        $this->student->save();
    }

    public function test_student_can_fetch_own_full_profile(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/students/me/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student' => [
                        'id' => $this->student->id,
                        'student_number' => $this->student->student_number,
                    ],
                ],
            ]);
    }

    public function test_student_can_request_profile_update(): void
    {
        $payload = [
            'phone_number' => '081299887766',
            'address' => 'Jl. Nusantara Baru No. 12, Jakarta',
            'notes' => 'Pembaruan alamat domisili baru',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson('/api/v1/students/me/request-update', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student' => [
                        'phone_number' => '081299887766',
                    ],
                ],
            ]);
    }

    public function test_student_can_fetch_khs_and_grades(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/students/me/khs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'semesters',
                    'courses',
                    'summary',
                ],
            ]);
    }

    public function test_student_can_fetch_class_schedules(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/students/me/schedules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}
