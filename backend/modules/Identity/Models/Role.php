<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * The users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Determine if the role has a specific permission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    /**
     * Give permissions to role by name or ID.
     */
    public function givePermissionTo(string|array|Permission $permission): self
    {
        $permissions = is_array($permission) ? $permission : [$permission];

        foreach ($permissions as $perm) {
            if (is_string($perm)) {
                $permModel = Permission::where('name', $perm)->first();
                if ($permModel) {
                    $this->permissions()->syncWithoutDetaching([$permModel->id]);
                }
            } elseif ($perm instanceof Permission) {
                $this->permissions()->syncWithoutDetaching([$perm->id]);
            } elseif (is_numeric($perm)) {
                $this->permissions()->syncWithoutDetaching([$perm]);
            }
        }

        return $this;
    }

    /**
     * Revoke permission from role.
     */
    public function revokePermissionTo(string|Permission $permission): self
    {
        if (is_string($permission)) {
            $permModel = Permission::where('name', $permission)->first();
            if ($permModel) {
                $this->permissions()->detach($permModel->id);
            }
        } elseif ($permission instanceof Permission) {
            $this->permissions()->detach($permission->id);
        }

        return $this;
    }

    /**
     * Sync permissions for the role.
     */
    public function syncPermissions(array $permissions): self
    {
        $ids = [];
        foreach ($permissions as $perm) {
            if (is_numeric($perm)) {
                $ids[] = $perm;
            } elseif (is_string($perm)) {
                $permModel = Permission::where('name', $perm)->first();
                if ($permModel) {
                    $ids[] = $permModel->id;
                }
            } elseif ($perm instanceof Permission) {
                $ids[] = $perm->id;
            }
        }

        $this->permissions()->sync($ids);
        return $this;
    }
}
