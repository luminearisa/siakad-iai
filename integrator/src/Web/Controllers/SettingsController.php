<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Support\Settings;
use Integrator\Support\View;
use Integrator\Web\Response;

/**
 * Settings screen: SIAKAD credentials, Neo Feeder connection, run defaults.
 */
final class SettingsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $settings = $this->app->settings();
        $check = $_SESSION['connection_check'] ?? null;
        unset($_SESSION['connection_check']);

        return Response::html($this->app->view()->page('settings/index', [
            'title' => 'Pengaturan',
            'values' => $settings->allMasked(),
            'check' => is_array($check) ? $check : null,
            'diagnostics' => $this->diagnostics(),
            'semesters' => $this->semesterOptions(),
        ]));
    }

    public function save(): Response
    {
        $input = $_POST;

        $rateKeys = ['batch_size', 'max_requests_per_run', 'request_delay_ms'];
        $values = [];

        foreach (array_keys(Settings::DEFAULTS) as $key) {
            $raw = $input[$key] ?? null;

            if (in_array($key, Settings::SECRET_KEYS, true)) {
                // Empty means "keep the stored secret" (handled by setMany()).
                $values[$key] = is_string($raw) ? $raw : null;

                continue;
            }

            // Ditulis otomatis oleh uji koneksi; formulir tidak boleh menghapusnya.
            if ($key === 'feeder_version_checked_at') {
                continue;
            }

            if (in_array($key, ['siakad_verify_ssl', 'feeder_sandbox', 'feeder_verify_ssl', 'dry_run', 'validate_before_push'], true)) {
                $values[$key] = $raw === null ? '0' : '1';

                continue;
            }

            if (in_array($key, $rateKeys, true)) {
                $values[$key] = is_numeric($raw) ? (string) max(0, (int) $raw) : '0';

                continue;
            }

            $values[$key] = is_string($raw) ? trim($raw) : null;
        }

        $values['feeder_payload_style'] = $values['feeder_payload_style'] === 'flat' ? 'flat' : 'record';

        $values['siakad_base_url'] = rtrim((string) $values['siakad_base_url'], '/');
        $values['feeder_base_url'] = rtrim((string) $values['feeder_base_url'], '/');

        $this->app->settings()->setMany($values);
        $this->app->refreshClients();

        View::flash('success', 'Pengaturan disimpan. Kredensial baru dipakai pada permintaan berikutnya.');

        return Response::redirect('/settings');
    }

    /**
     * Store a semester default without touching anything else.
     */
    public function defaultSemester(): Response
    {
        $this->app->settings()->set('default_semester_code', trim((string) ($_POST['default_semester_code'] ?? '')));

        View::flash('success', 'Semester default diperbarui.');

        return Response::redirect('/settings');
    }

    /**
     * Environment report — the first screen to open when something misbehaves.
     *
     * @return array<string, array{label: string, value: string, ok: bool}>
     */
    private function diagnostics(): array
    {
        $storagePath = $this->app->root('storage');

        return [
            'php' => [
                'label' => 'Versi PHP',
                'value' => PHP_VERSION,
                'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
            'curl' => [
                'label' => 'Ekstensi cURL',
                'value' => extension_loaded('curl') ? 'aktif' : 'tidak aktif',
                'ok' => extension_loaded('curl'),
            ],
            'openssl' => [
                'label' => 'Ekstensi OpenSSL',
                'value' => extension_loaded('openssl') ? 'aktif' : 'tidak aktif',
                'ok' => extension_loaded('openssl'),
            ],
            'sqlite' => [
                'label' => 'Ekstensi PDO SQLite',
                'value' => extension_loaded('pdo_sqlite') ? 'aktif' : 'tidak aktif',
                'ok' => extension_loaded('pdo_sqlite'),
            ],
            'storage' => [
                'label' => 'Folder storage dapat ditulis',
                'value' => is_writable($storagePath) ? $storagePath : $storagePath.' (tidak writable)',
                'ok' => is_writable($storagePath),
            ],
            'key' => [
                'label' => 'APP_KEY tersedia',
                'value' => \Integrator\Support\Config::appKey() !== '' ? 'ya' : 'tidak',
                'ok' => \Integrator\Support\Config::appKey() !== '',
            ],
            'mapping' => [
                'label' => 'Entity pada feeder_mapping.php',
                'value' => (string) count($this->app->mapper()->entities()).' entity data',
                'ok' => count($this->app->mapper()->entities()) > 0,
            ],
        ];
    }

    /**
     * Semester list from SIAKAD, for the default-semester picker.
     *
     * @return array<int, array<string, mixed>>
     */
    private function semesterOptions(): array
    {
        if (! $this->app->settings()->siakadConfigured()) {
            return [];
        }

        try {
            return $this->app->siakad()->semesters();
        } catch (\Throwable) {
            return [];
        }
    }
}
