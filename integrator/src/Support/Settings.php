<?php

declare(strict_types=1);

namespace Integrator\Support;

/**
 * Key/value store for everything configurable from the UI.
 *
 * Secrets (SIAKAD API key, Neo Feeder password) are encrypted at rest; all other
 * values are plain so operators can inspect them with a SQLite browser.
 */
final class Settings
{
    /** Keys whose values are encrypted before they touch the disk. */
    public const SECRET_KEYS = [
        'siakad_api_key',
        'feeder_password',
    ];

    public const DEFAULTS = [
        'siakad_base_url' => 'http://localhost:8000',
        'siakad_api_key' => '',
        'siakad_verify_ssl' => '1',
        'feeder_base_url' => 'http://localhost:8100',
        'feeder_username' => '',
        'feeder_password' => '',
        'feeder_sandbox' => '1',
        'feeder_verify_ssl' => '0',
        'default_semester_code' => '',
        'dry_run' => '1',
        'batch_size' => '100',
        'max_requests_per_run' => '0',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly Crypto $crypto
    ) {
    }

    /**
     * Value of a setting: database → `.env` → default.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->db->first('SELECT value, is_secret FROM settings WHERE key = :key', ['key' => $key]);

        if ($row !== null) {
            $value = (string) ($row['value'] ?? '');

            if ((int) ($row['is_secret'] ?? 0) === 1) {
                return $this->crypto->decrypt($value);
            }

            return $value;
        }

        $fromEnv = Config::env(strtoupper($key));

        if ($fromEnv !== null && $fromEnv !== '') {
            return $fromEnv;
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $isSecret = in_array($key, self::SECRET_KEYS, true);
        $stored = $value ?? '';

        if ($isSecret && $stored !== '') {
            $stored = $this->crypto->encrypt($stored);
        }

        $this->db->upsert('settings', [
            'key' => $key,
            'value' => $stored,
            'is_secret' => $isSecret ? 1 : 0,
            'updated_at' => $this->db->now(),
        ], ['key']);
    }

    /**
     * Persist a batch of settings, skipping blank secret fields so an operator
     * cannot wipe a stored credential by submitting an empty form.
     *
     * @param  array<string, string|null>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && ($value === null || $value === '')) {
                continue;
            }

            $this->set($key, $value);
        }
    }

    /**
     * All settings with secrets masked, for the settings screen.
     *
     * @return array<string, string>
     */
    public function allMasked(): array
    {
        $values = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $value = $this->get($key);

            $values[$key] = in_array($key, self::SECRET_KEYS, true)
                ? Crypto::mask($value)
                : (string) ($value ?? '');
        }

        return $values;
    }

    /**
     * Whether the SIAKAD side is configured well enough to pull data.
     */
    public function siakadConfigured(): bool
    {
        return trim((string) $this->get('siakad_base_url')) !== ''
            && trim((string) $this->get('siakad_api_key')) !== '';
    }

    /**
     * Whether the feeder side is configured well enough to push data.
     */
    public function feederConfigured(): bool
    {
        return trim((string) $this->get('feeder_base_url')) !== ''
            && trim((string) $this->get('feeder_username')) !== ''
            && trim((string) $this->get('feeder_password')) !== '';
    }
}
