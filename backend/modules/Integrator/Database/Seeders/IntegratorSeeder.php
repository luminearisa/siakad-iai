<?php

namespace Modules\Integrator\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Settings\Models\Setting;

/**
 * Permissions, role grants and defaults for the Integrator module.
 *
 * Note: `admin_akademik` is granted explicitly. The role is assembled in
 * `IdentitySeeder` by permission *group*, so a new group added later silently
 * leaves that role unable to use the module — the exact failure mode that hit the
 * MBKM module. Registering the grants here keeps both seeders independent.
 */
class IntegratorSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'integrator.clients.view', 'display_name' => 'View Integration Clients', 'group' => 'integrator', 'description' => 'View API clients'],
            ['name' => 'integrator.clients.manage', 'display_name' => 'Manage Integration Clients', 'group' => 'integrator', 'description' => 'Create, update, deactivate API clients'],
            ['name' => 'integrator.keys.view', 'display_name' => 'View API Keys', 'group' => 'integrator', 'description' => 'View API keys (prefix and metadata only)'],
            ['name' => 'integrator.keys.manage', 'display_name' => 'Manage API Keys', 'group' => 'integrator', 'description' => 'Issue, rotate and revoke API keys'],
            ['name' => 'integrator.logs.view', 'display_name' => 'View Integration Log', 'group' => 'integrator', 'description' => 'View the integration access log and statistics'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $integratorPermissions = Permission::where('group', 'integrator')->pluck('id');

        foreach (['super_admin', 'admin_akademik'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($integratorPermissions);

            if ($roleName === 'super_admin') {
                $role->permissions()->sync(Permission::pluck('id'));
            }
        }

        Setting::firstOrCreate(
            ['key' => 'integrator.default_rate_limit'],
            [
                'value' => '120',
                'type' => 'integer',
                'group' => 'integrator',
                'description' => 'Default requests per minute for a new integration client',
            ]
        );
    }
}
