<?php

declare(strict_types=1);

namespace Integrator\Support;

/**
 * Configuration bootstrap for the standalone integrator.
 *
 * Values are resolved in this order: `.env` file → environment variables →
 * database settings (managed from the Settings page) → caller default.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $env = [];

    private static string $root = '';

    public static function boot(string $root): void
    {
        self::$root = rtrim($root, '/');
        self::loadEnvFile(self::$root.'/.env');
    }

    public static function root(string $path = ''): string
    {
        return $path === '' ? self::$root : self::$root.'/'.ltrim($path, '/');
    }

    /**
     * Read a raw environment variable.
     */
    public static function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return self::$env[$key] ?? $default;
        }

        return (string) $value;
    }

    /**
     * Ensure `.env` and `APP_KEY` exist so a fresh checkout can be started with
     * `php -S` without any setup step.
     */
    public static function ensureEnvironment(): void
    {
        $envPath = self::$root.'/.env';

        if (! is_file($envPath)) {
            $example = self::$root.'/.env.example';

            if (is_file($example)) {
                copy($example, $envPath);
            } else {
                file_put_contents($envPath, "APP_ENV=local\n");
            }
        }

        self::loadEnvFile($envPath);

        if (empty(self::$env['APP_KEY'])) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            file_put_contents($envPath, PHP_EOL."APP_KEY={$key}".PHP_EOL, FILE_APPEND);
            self::$env['APP_KEY'] = $key;
        }
    }

    /**
     * Application key used for encrypting stored secrets.
     */
    public static function appKey(): string
    {
        $value = self::env('APP_KEY', '') ?? '';

        if (str_starts_with($value, 'base64:')) {
            return base64_decode(substr($value, 7)) ?: '';
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    public static function dottedEnv(): array
    {
        return self::$env;
    }

    private static function loadEnvFile(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, "\"'");
            }

            self::$env[$key] = $value;
        }
    }
}
