<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * Dot-path reader for nested arrays with a null-safe default.
 */
final class ArrayPath
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function get(array $row, ?string $path, mixed $default = null): mixed
    {
        if ($path === null || $path === '') {
            return $default;
        }

        if (array_key_exists($path, $row)) {
            return $row[$path];
        }

        $value = $row;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            return $default;
        }

        return $value;
    }
}
