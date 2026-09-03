<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;

class LoginUserAction
{
    /**
     * Authenticate a user and create a token.
     *
     * @throws ValidationException
     */
    public function execute(string $email, string $password, ?string $deviceName = null): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'email' => ['Your account is ' . ($user->status->value ?? 'inactive') . '. Please contact administrator.'],
            ]);
        }

        $tokenName = $deviceName ?: 'api_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user->load('roles.permissions'),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
