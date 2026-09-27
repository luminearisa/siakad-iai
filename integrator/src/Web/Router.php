<?php

declare(strict_types=1);

namespace Integrator\Web;

use Integrator\App;
use Integrator\Support\View;
use Throwable;

/**
 * Very small router: exact paths plus `{parameter}` segments.
 *
 * Every route is behind the login session unless it is explicitly marked public, and
 * POST requests must carry the session CSRF token — the dashboard can push records
 * into PDDikti, so it deserves at least the basics.
 */
final class Router
{
    /** @var array<int, array{method: string, path: string, handler: array{0: class-string, 1: string}, auth: bool, csrf: bool}> */
    private array $routes = [];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param  array{0: class-string, 1: string}  $handler
     */
    public function get(string $path, array $handler, bool $auth = true): void
    {
        $this->add('GET', $path, $handler, $auth);
    }

    /**
     * @param  array{0: class-string, 1: string}  $handler
     */
    public function post(string $path, array $handler, bool $auth = true, bool $csrf = true): void
    {
        $this->add('POST', $path, $handler, $auth, $csrf);
    }

    /**
     * @param  array{0: class-string, 1: string}  $handler
     */
    private function add(string $method, string $path, array $handler, bool $auth, bool $csrf = true): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => trim($path, '/'),
            'handler' => $handler,
            'auth' => $auth,
            'csrf' => $csrf,
        ];
    }

    public function dispatch(string $method, string $uri): Response
    {
        $path = trim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            $parameters = $this->match($route['path'], $path);

            if ($parameters === null) {
                continue;
            }

            if ($route['method'] !== $method) {
                continue;
            }

            if ($route['auth'] && ! $this->app->auth()->check()) {
                return Response::redirect('/login');
            }

            if ($route['method'] === 'POST' && $route['csrf'] && ! $this->validCsrf()) {
                return Response::html(
                    $this->app->view()->page('errors/error', [
                        'title' => 'Sesi kedaluwarsa',
                        'message' => 'Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.',
                    ]),
                    419
                );
            }

            [$class, $action] = $route['handler'];

            try {
                return (new $class($this->app))->{$action}(...array_values($parameters));
            } catch (Throwable $exception) {
                return $this->error($exception);
            }
        }

        return Response::html(
            $this->app->view()->page('errors/error', [
                'title' => 'Halaman tidak ditemukan',
                'message' => 'Alamat yang Anda tuju tidak ada: /'.$path,
            ]),
            404
        );
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $routePath, string $actual): ?array
    {
        if ($routePath === $actual) {
            return [];
        }

        if (! str_contains($routePath, '{')) {
            return null;
        }

        $pattern = '#^'.preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePath).'$#';

        if (preg_match($pattern, $actual, $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    private function validCsrf(): bool
    {
        $token = $_POST['_token'] ?? '';

        return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    private function error(Throwable $exception): Response
    {
        $debug = (\Integrator\Support\Config::env('APP_DEBUG', '1') ?? '1') === '1';

        return Response::html(
            $this->app->view()->page('errors/error', [
                'title' => 'Terjadi kesalahan',
                'message' => $exception->getMessage(),
                'trace' => $debug ? $exception->getTraceAsString() : null,
            ]),
            500
        );
    }
}
