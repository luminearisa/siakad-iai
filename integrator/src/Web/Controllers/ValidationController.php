<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Reporting\ValidationOptions;
use Integrator\Support\View;
use Integrator\Validation\Violation;
use Integrator\Web\Response;
use Throwable;

/**
 * Pemeriksaan kesiapan pelaporan: data mana yang akan ditolak Neo Feeder dan
 * seberapa lengkap laporan per program studi.
 *
 * Padanan halaman ini pada aplikasi komersial (ProFeeder) adalah "Validasi data
 * akademik", "Rekap data tidak valid", dan "Persentase pelaporan per prodi".
 */
final class ValidationController
{
    private const PER_PAGE = 50;

    public function __construct(private readonly App $app) {}

    public function index(): Response
    {
        $repository = $this->app->validationRepository();
        $run = $repository->latestRun();

        $filters = array_filter([
            'severity' => $this->query('severity'),
            'entity' => $this->query('entity'),
            'code' => $this->query('code'),
            'search' => $this->query('search'),
        ], static fn ($value) => $value !== null && $value !== '');

        $page = max(1, (int) ($this->query('page') ?? 1));
        $total = $repository->countFindings($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        $version = $this->app->settings()->get('feeder_version');

        return Response::html($this->app->view()->page('validation/index', [
            'title' => 'Validasi Pelaporan',
            'run' => $run,
            'runs' => $repository->runs(8),
            'findings' => $repository->findings($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'totalFindings' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'byCode' => $run !== null ? $repository->findingsByCode((string) $run['run_id'], 12) : [],
            'coverage' => $repository->coverageByEntity(),
            'coverageProdi' => $repository->coverageByProdi(null, 20),
            'coverageTotals' => $repository->coverageTotals(),
            'entities' => array_keys($this->app->registry()->all()),
            'semester' => (string) ($this->app->settings()->get('default_semester_code') ?? ''),
            'feederVersion' => is_string($version) ? $version : null,
            'minimumVersion' => $this->app->validator()->minimumFeederVersion(),
            'versionNote' => $this->app->validator()->minimumFeederVersionNote(),
            'versionOk' => $this->app->validator()->versionCompatible(is_string($version) ? $version : null),
            'versionCheckedAt' => $this->app->settings()->get('feeder_version_checked_at'),
        ]));
    }

    /**
     * Jalankan pemeriksaan (validasi + hitung cakupan) untuk satu entity atau semua.
     */
    public function run(): Response
    {
        $entity = trim((string) ($_POST['entity'] ?? ''));
        $semester = trim((string) ($_POST['semester_code'] ?? ''));
        $limit = (int) ($_POST['limit'] ?? 0);

        try {
            $summary = $this->app->validationPass()->run(ValidationOptions::fromArray([
                'entity' => $entity !== '' ? $entity : null,
                'semester' => $semester !== '' ? $semester : null,
                'limit' => $limit,
            ]));
        } catch (Throwable $exception) {
            View::flash('error', 'Pemeriksaan tidak bisa dijalankan: '.$exception->getMessage());

            return Response::redirect('/validation');
        }

        $message = 'Pemeriksaan selesai. '.$summary->summary();

        if ($summary->invalid() > 0) {
            View::flash('error', $message.' — periksa daftar temuan di bawah sebelum mengirim data.');
        } elseif ($summary->errors !== []) {
            View::flash('error', $message.' Sebagian entity tidak bisa diperiksa: '.implode(' | ', $summary->errors));
        } else {
            View::flash('success', $message);
        }

        return Response::redirect('/validation');
    }

    /**
     * Hapus riwayat temuan lama agar tabel tetap ringan.
     */
    public function purge(): Response
    {
        $days = (int) ($_POST['days'] ?? 30);
        $removed = $this->app->validationRepository()->purge($days);

        View::flash('success', $removed === 0
            ? 'Tidak ada riwayat validasi yang perlu dihapus.'
            : "Riwayat {$removed} pemeriksaan lama dihapus.");

        return Response::redirect('/validation');
    }

    private function query(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
