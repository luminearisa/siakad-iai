<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@siakad.ac.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'status', 'roles', 'permissions'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'admin@siakad.ac.id',
                    ],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@siakad.ac.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_login_fails_for_inactive_or_suspended_user(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@siakad.ac.id',
            'password' => Hash::make('password123'),
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@siakad.ac.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::where('email', 'admin@siakad.ac.id')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'admin@siakad.ac.id',
                ],
            ]);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::where('email', 'admin@siakad.ac.id')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::where('email', 'dosen@siakad.ac.id')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'newsecretpass123',
                'new_password_confirmation' => 'newsecretpass123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        $this->assertTrue(Hash::check('newsecretpass123', $user->fresh()->password));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
