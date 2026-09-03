<?php

namespace Modules\Identity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException('Unauthenticated user.');
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        $allPermissions = [];
        foreach ($permissions as $p) {
            foreach (explode('|', $p) as $sub) {
                $allPermissions[] = trim($sub);
            }
        }

        foreach ($allPermissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException('User does not have the required permission.');
    }
}
