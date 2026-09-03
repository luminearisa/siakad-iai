<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->token = $admin->createToken('test_token')->plainTextToken;
    }

    public function test_consistent_success_response_structure(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_consistent_paginated_response_structure(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_consistent_validation_error_response_structure(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ]);
    }

    public function test_consistent_404_not_found_response_structure(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/academic/faculties/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }
}
