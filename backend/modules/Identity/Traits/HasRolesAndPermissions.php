<?php

namespace Modules\Identity\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;

trait HasRolesAndPermissions
{
    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Determine if the user has any of the given roles.
     */
    public function hasRole(string|array ...$roles): bool
    {
        $roleNames = collect($roles)->flatten()->toArray();

        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Determine if the user has a given permission (via roles).
     */
    public function hasPermissionTo(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('name', $permission);
            })
            ->exists();
    }

    /**
     * Get all permissions assigned to the user across all roles.
     */
    public function allPermissions(): Collection
    {
        if ($this->hasRole('super_admin')) {
            return Permission::all();
        }

        return $this->roles->loadMissing('permissions')->flatMap(function (Role $role) {
            return $role->permissions;
        })->unique('id')->values();
    }

    /**
     * Assign role(s) to the user.
     */
    public function assignRole(string|array|Role ...$roles): self
    {
        $flatRoles = collect($roles)->flatten();

        foreach ($flatRoles as $r) {
            if (is_string($r)) {
                $roleModel = Role::where('name', $r)->first();
                if ($roleModel) {
                    $this->roles()->syncWithoutDetaching([$roleModel->id]);
                }
            } elseif ($r instanceof Role) {
                $this->roles()->syncWithoutDetaching([$r->id]);
            } elseif (is_numeric($r)) {
                $this->roles()->syncWithoutDetaching([$r]);
            }
        }

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string|Role $role): self
    {
        if (is_string($role)) {
            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $this->roles()->detach($roleModel->id);
            }
        } elseif ($role instanceof Role) {
            $this->roles()->detach($role->id);
        }

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Sync roles for the user.
     */
    public function syncRoles(array $roles): self
    {
        $ids = [];
        foreach ($roles as $role) {
            if (is_numeric($role)) {
                $ids[] = $role;
            } elseif (is_string($role)) {
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $ids[] = $roleModel->id;
                }
            } elseif ($role instanceof Role) {
                $ids[] = $role->id;
            }
        }

        $this->roles()->sync($ids);
        $this->unsetRelation('roles');

        return $this;
    }
}
