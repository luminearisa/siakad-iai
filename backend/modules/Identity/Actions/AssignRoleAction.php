<?php

namespace Modules\Identity\Actions;

use Modules\Identity\Models\User;

class AssignRoleAction
{
    /**
     * Assign roles to user.
     */
    public function execute(User $user, array $roles): User
    {
        $user->syncRoles($roles);
        return $user->load('roles.permissions');
    }
}
