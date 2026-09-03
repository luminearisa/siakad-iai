<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Super Admin bypasses all authorization checks
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        // Dynamic permission check fallback for Gate::allows / $user->can()
        Gate::after(function ($user, $ability, $result, $arguments) {
            if ($result !== null) {
                return $result;
            }

            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($ability);
            }

            return false;
        });
    }
}
