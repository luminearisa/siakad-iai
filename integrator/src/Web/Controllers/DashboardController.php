<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Siakad\SiakadException;
use Integrator\Web\Response;
use Throwable;

/**
 * Overview screen: configuration state, upstream reachability, ledger counters.
 */
final class DashboardController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $settings = $this->app->settings();
        $check = $this->pullConnectionCheck();

        $snapshot = null;
        $siakadError = null;

        if ($settings->siakadConfigured()) {
            try {
                $snapshot = $this->app->siakad()->snapshot();
            } catch (SiakadException $exception) {
                $siakadError = ['message' => $exception->getMessage(), 'hint' => $exception->hint()];
            }
        }

        return Response::html($this->app->view()->page('dashboard/index', [
            'title' => 'Dashboard',
            'settings' => $settings,
            'snapshot' => $snapshot,
            'siakadError' => $siakadError,
            'check' => $check,
            'mappingCounts' => $this->app->mappings()->countsByEntity(),
            'referenceCounts' => $this->app->references()->counts(),
            'totals' => $this->app->logRepository()->totalsByStatus(),
            'runs' => $this->app->logRepository()->runs(10),
            'failures' => $this->app->logRepository()->topFailures(5),
            'entities' => $this->app->registry()->all(),
            'referenceFresh' => $this->app->references()->isFresh(),
        ]));
    }

    /**
     * Run both connection checks on demand (the button on the dashboard).
     */
    public function check(): Response
    {
        $result = ['siakad' => null, 'feeder' => null];

        if ($this->app->settings()->siakadConfigured()) {
            try {
                $ping = $this->app->siakad()->ping();

                $result['siakad'] = [
                    'ok' => true,
                    'message' => 'Terhubung sebagai '.($ping['client']['name'] ?? 'klien tidak dikenal'),
                    'details' => [
                        'Aplikasi' => (string) ($ping['application'] ?? '-'),
                        'Scope' => implode(', ', (array) ($ping['key']['scopes'] ?? [])),
                        'Kedaluwarsa key' => (string) ($ping['key']['expires_at'] ?? 'tidak ada'),
                        'Waktu server' => (string) ($ping['server_time'] ?? '-'),
                    ],
                ];
            } catch (SiakadException $exception) {
                $result['siakad'] = ['ok' => false, 'message' => $exception->getMessage(), 'details' => ['Saran' => $exception->hint()]];
            }
        } else {
            $result['siakad'] = ['ok' => false, 'message' => 'Base URL atau API key SIAKAD belum diisi.', 'details' => []];
        }

        if ($this->app->settings()->feederConfigured()) {
            try {
                $ping = $this->app->feeder()->ping();
                $profile = $ping['profile'] ?? [];

                $result['feeder'] = [
                    'ok' => $ping['error_code'] === 0,
                    'message' => $ping['error_code'] === 0
                        ? 'Login berhasil (mode '.($ping['sandbox'] ? 'sandbox' : 'live').')'
                        : (string) $ping['error_desc'],
                    'details' => array_filter([
                        'Endpoint' => (string) $ping['endpoint'],
                        'PT' => (string) ($profile['nama_perguruan_tinggi'] ?? ''),
                        'Kode PT' => (string) ($profile['kode_perguruan_tinggi'] ?? ''),
                    ]),
                ];
            } catch (Throwable $exception) {
                $result['feeder'] = ['ok' => false, 'message' => $exception->getMessage(), 'details' => []];
            }
        } else {
            $result['feeder'] = ['ok' => false, 'message' => 'Host/username/password Neo Feeder belum diisi.', 'details' => []];
        }

        $_SESSION['connection_check'] = $result;

        return Response::redirect('/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pullConnectionCheck(): ?array
    {
        $check = $_SESSION['connection_check'] ?? null;
        unset($_SESSION['connection_check']);

        return is_array($check) ? $check : null;
    }
}
