<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;

class ChangePasswordAction
{
    /**
     * Change user's password.
     */
    public function execute(User $user, string $newPassword): bool
    {
        $user->password = Hash::make($newPassword);
        return $user->save();
    }
}
