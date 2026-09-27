<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Sync\SyncOptions;
use Integrator\Support\View;
use Integrator\Web\Response;
use RuntimeException;

/**
 * The sync workshop: pick an entity, choose the semester, run it in dry-run or live.
 */
final class SyncController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $settings = $this->app->settings();

        $statuses = [];
        foreach ($this->app->logRepository()->runs(50) as $run) {
            $statuses[(string) $run['entity']] ??= $run;
        }

        return Response::html($this->app->view()->page('sync/index', [
            'title' => 'Sinkronisasi',
            'groups' => $this->app->registry()->grouped(),
            'mappingCounts' => $this->app->mappings()->countsByEntity(),
            'statuses' => $statuses,
            'semesters' => $this->semesterOptions(),
            'lastRun' => $this->app->logRepository()->runs(1)[0] ?? null,
            'values' => [
                'semester_code' => (string) ($settings->get('default_semester_code') ?? ''),
                'dry_run' => $settings->bool('dry_run', true),
                'limit' => 0,
                'force' => false,
            ],
        ]));
    }

    public function run(): Response
    {
        if (! $this->app->settings()->feederConfigured() && ! $this->inputBool('dry_run')) {
            View::flash('error', 'Mode live memerlukan konfigurasi Neo Feeder (host, username, password).');

            return Response::redirect('/sync');
        }

        $entity = trim((string) ($_POST['entity'] ?? ''));
        $withDependencies = $this->inputBool('with_dependencies');

        if (! $this->app->registry()->has($entity)) {
            View::flash('error', 'Entity tidak dikenal.');

            return Response::redirect('/sync');
        }

        $options = SyncOptions::fromArray([
            'dry_run' => $this->inputBool('dry_run'),
            'semester_code' => $_POST['semester_code'] ?? null,
            'semester_id' => $this->semesterIdFor($_POST['semester_code'] ?? null),
            'limit' => $_POST['limit'] ?? 0,
            'force' => $this->inputBool('force'),
        ]);

        $order = $withDependencies ? $this->app->registry()->order($entity) : [$entity];

        $results = [];
        $failed = false;

        foreach ($order as $current) {
            $result = $this->app->runner()->run($current, $options);
            $results[] = $result;

            if (! $result->ok() || $result->failed > 0) {
                $failed = true;

                // Stop the chain: pushing children of rows that failed to insert
                // would only produce cascades of "id_kelas tidak ditemukan".
                if ($withDependencies && $current !== $entity) {
                    break;
                }
            }
        }

        $last = end($results);
        $summary = implode(' | ', array_map(static fn ($result) => $result->summary(), $results));

        View::flash($failed ? 'error' : 'success', $summary);

        return Response::redirect('/sync/run/'.($last->runId ?? ''));
    }

    public function show(string $runId): Response
    {
        $run = $this->app->logRepository()->run($runId);

        if ($run === null) {
            View::flash('error', 'Riwayat sinkronisasi tidak ditemukan.');

            return Response::redirect('/sync');
        }

        $filters = [
            'run_id' => $runId,
            'status' => $_GET['status'] ?? null,
        ];

        return Response::html($this->app->view()->page('sync/show', [
            'title' => 'Hasil Sinkronisasi',
            'run' => $run,
            'statusCounts' => $this->app->logRepository()->statusCounts($runId),
            'filters' => $filters,
            'logs' => $this->app->logRepository()->logs($filters, 200),
            'entity' => $this->app->registry()->has((string) $run['entity']) ? $this->app->registry()->get((string) $run['entity']) : null,
            'preview' => $this->app->registry()->has((string) $run['entity'])
                ? $this->app->mapper()->preview($this->app->registry()->get((string) $run['entity'])->config())
                : [],
        ]));
    }

    /**
     * Retry a single row (button on the run detail screen).
     */
    public function retryRow(): Response
    {
        $entity = trim((string) ($_POST['entity'] ?? ''));
        $localKey = trim((string) ($_POST['local_key'] ?? ''));

        if (! $this->app->registry()->has($entity) || $localKey === '') {
            View::flash('error', 'Data baris tidak lengkap.');

            return Response::redirect('/sync');
        }

        $options = SyncOptions::fromArray([
            'dry_run' => $this->inputBool('dry_run'),
            'semester_code' => $_POST['semester_code'] ?? null,
            'semester_id' => $this->semesterIdFor($_POST['semester_code'] ?? null),
            'limit' => 0,
            'force' => true,
            'local_key' => $localKey,
        ]);

        $result = $this->app->runner()->run($entity, $options);

        View::flash($result->failed > 0 ? 'error' : 'success', 'Ulang baris '.$localKey.': '.$result->summary());

        return Response::redirect('/sync/run/'.$result->runId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function semesterOptions(): array
    {
        if (! $this->app->settings()->siakadConfigured()) {
            return [];
        }

        try {
            return $this->app->siakad()->semesters();
        } catch (RuntimeException) {
            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Translate a PDDikti semester code into the SIAKAD semester id.
     */
    private function semesterIdFor(mixed $code): ?int
    {
        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        foreach ($this->semesterOptions() as $semester) {
            if ((string) ($semester['feeder_code'] ?? '') === trim($code)) {
                return (int) $semester['id'];
            }
        }

        return null;
    }

    private function inputBool(string $key): bool
    {
        $value = $_POST[$key] ?? null;

        return in_array((string) $value, ['1', 'on', 'true', 'yes'], true);
    }
}
