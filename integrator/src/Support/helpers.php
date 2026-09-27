<?php

declare(strict_types=1);

/**
 * Global template helpers. Deliberately few: escaping, URLs, CSRF, formatting.
 */

if (! function_exists('e')) {
    /**
     * Escape a value for HTML output.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('url')) {
    function url(string $path = '/'): string
    {
        return '/'.ltrim($path, '/');
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
    }
}

if (! function_exists('selected')) {
    /**
     * `selected` attribute helper for form controls.
     */
    function selected(mixed $value, mixed $expected): string
    {
        return (string) $value === (string) $expected ? ' selected' : '';
    }
}

if (! function_exists('checked')) {
    function checked(mixed $value): string
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? ' checked' : '';
    }
}

if (! function_exists('status_badge')) {
    /**
     * CSS class for a sync log status.
     */
    function status_badge(string $status): string
    {
        return match ($status) {
            'succeeded' => 'badge badge-ok',
            'planned' => 'badge badge-info',
            'skipped' => 'badge badge-muted',
            'failed' => 'badge badge-danger',
            default => 'badge',
        };
    }
}

if (! function_exists('human_datetime')) {
    function human_datetime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('d M Y H:i', $timestamp);
    }
}

if (! function_exists('short_json')) {
    function short_json(?string $json, int $limit = 160): string
    {
        if ($json === null || $json === '') {
            return '';
        }

        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            $json = (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return mb_strlen($json) > $limit ? mb_substr($json, 0, $limit).'…' : $json;
    }
}
