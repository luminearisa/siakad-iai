<?php

namespace Modules\Identity\Services;

use Modules\Identity\Actions\AssignRoleAction;
use Modules\Identity\Actions\ChangePasswordAction;
use Modules\Identity\Actions\LoginUserAction;
use Modules\Identity\Actions\LogoutUserAction;
use Modules\Identity\Models\User;

class IdentityService
{
    public function __construct(
        protected LoginUserAction $loginUserAction,
        protected LogoutUserAction $logoutUserAction,
        protected ChangePasswordAction $changePasswordAction,
        protected AssignRoleAction $assignRoleAction
    ) {}

    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        return $this->loginUserAction->execute($email, $password, $deviceName);
    }

    public function logout(User $user): bool
    {
        return $this->logoutUserAction->execute($user);
    }

    public function changePassword(User $user, string $newPassword): bool
    {
        return $this->changePasswordAction->execute($user, $newPassword);
    }

    public function assignRoles(User $user, array $roles): User
    {
        return $this->assignRoleAction->execute($user, $roles);
    }
}
