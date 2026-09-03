<?php

namespace Modules\Identity\Actions;

use Modules\Identity\Models\User;

class LogoutUserAction
{
    /**
     * Revoke the current access token of the user.
     */
    public function execute(User $user): bool
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
            return true;
        }

        return false;
    }
}
