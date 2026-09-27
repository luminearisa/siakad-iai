<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * Renders `{dotted.path}` templates against a source row.
 *
 * Used for local keys (`nim:{nim}`, `class:{class_code}|nidn:{item.nidn}`) and for
 * cross-entity lookups (`@mapping:classes:class:{class_code}`).
 */
final class Template
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function render(?string $template, array $row): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return (string) preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', static function (array $matches) use ($row): string {
            $value = ArrayPath::get($row, $matches[1]);

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if ($value === null || is_array($value)) {
                return '';
            }

            return (string) $value;
        }, $template);
    }
}
