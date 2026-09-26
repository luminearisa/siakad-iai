<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Student\Enums\StudentStatus;

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

        $student = $user->student()->first();

        if ($student && $student->status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'email' => [
                    'Status kemahasiswaan Anda adalah "' . $student->status->value
                    . '", sehingga tidak dapat masuk ke sistem. Hubungi bagian akademik.',
                ],
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
