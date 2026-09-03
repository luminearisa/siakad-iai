<?php

namespace Modules\Identity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException('Unauthenticated user.');
        }

        if (!$user->hasRole($roles)) {
            throw new AccessDeniedHttpException('User does not have the required role.');
        }

        return $next($request);
    }
}
