<?php

namespace Modules\Identity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blocks every authenticated request until a system-generated password has been
 * rotated, except for the endpoints needed to perform that rotation.
 */
class EnsurePasswordIsRotated
{
    private const EXEMPT = [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/me',
        'api/v1/auth/change-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustChangePassword() && !in_array($request->path(), self::EXEMPT, true)) {
            throw new AccessDeniedHttpException(
                'Anda wajib mengganti password bawaan sistem terlebih dahulu sebelum menggunakan fitur lain.'
            );
        }

        return $next($request);
    }
}
