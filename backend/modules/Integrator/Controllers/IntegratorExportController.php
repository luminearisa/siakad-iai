<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Integrator\Services\IntegratorExportService;
use Modules\Integrator\Support\ExportDownload;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan ("Export as…") untuk data yang berkaitan dengan pelaporan PDDikti /
 * Neo Feeder.
 *
 * Dua kelompok:
 *  1. data integrasi — log akses, rekap harian, daftar klien & kunci API;
 *  2. data pelaporan feeder — isi halaman Data Mahasiswa, Data Dosen, Mata Kuliah,
 *     Kurikulum, Kelas, KRS, AKM, Nilai, Lulusan, Aktivitas, dan data referensi
 *     (prodi/fakultas/PT/tahun ajaran/ruang). Datanya diambil dari
 *     {@see IntegratorDataService} yang sama dengan endpoint `/integrator/v1/*`,
 *     sehingga berkas unduhan identik dengan yang ditarik feeder.
 *
 * Format:
 *  - `?format=csv` (bawaan) — CSV UTF-8 dengan BOM supaya langsung rapi di Excel;
 *  - `?format=json` — arsip terstruktur (meta + headers + data) untuk tim integrator;
 *  - `?header=api` — judul kolom memakai nama field mentah (mis. `nim`, `sks`).
 *
 * Unduhan dibatasi {@see IntegratorExportService::MAX_ROWS} baris per berkas dan
 * setiap unduhan dicatat ke log aplikasi sebagai jejak audit.
 */
class IntegratorExportController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly IntegratorExportService $exports,
    ) {}

    /**
     * Log permintaan API mengikuti filter yang sedang dipakai operator.
     */
    public function logs(Request $request): StreamedResponse
    {
        $filters = $this->logFilters($request);

        return $this->download(
            request: $request,
            dataset: 'log-akses-integrasi',
            basename: 'log-integrasi-pddikti',
            data: $this->exports->requestLogs($filters),
            filters: $filters,
        );
    }

    /**
     * Rekap harian per klien (lampiran laporan pelaporan).
     */
    public function summary(Request $request): StreamedResponse
    {
        $filters = $this->logFilters($request);

        return $this->download(
            request: $request,
            dataset: 'rekap-log-integrasi',
            basename: 'rekap-integrasi-pddikti',
            data: $this->exports->requestSummary($filters),
            filters: $filters,
        );
    }

    /**
     * Klien integrasi + ringkasan kuncinya.
     */
    public function clients(Request $request): StreamedResponse
    {
        return $this->download(
            request: $request,
            dataset: 'klien-integrasi',
            basename: 'klien-integrasi-pddikti',
            data: $this->exports->clients(),
            filters: [],
        );
    }

    /**
     * Kunci API (prefix, scope, masa berlaku) — tanpa rahasia apa pun.
     */
    public function keys(Request $request): StreamedResponse
    {
        $filters = array_filter([
            'api_client_id' => $request->query('api_client_id'),
            'status' => $request->query('status'),
        ], static fn ($value) => $value !== null && $value !== '');

        return $this->download(
            request: $request,
            dataset: 'kunci-api-integrasi',
            basename: 'kunci-integrasi-pddikti',
            data: $this->exports->keys($filters),
            filters: $filters,
        );
    }

    /**
     * Katalog dataset pelaporan feeder yang boleh diunduh pengguna ini.
     */
    public function datasets(Request $request): JsonResponse
    {
        $catalog = $this->exports->datasetCatalog($request->user());

        return $this->successResponse(
            data: ['datasets' => $catalog],
            message: 'Daftar dataset yang dapat diunduh berhasil diambil.',
        );
    }

    /**
     * Unduhan satu dataset pelaporan feeder ("Export as…" di halaman data SIAKAD).
     *
     * Permission mengikuti halaman asalnya (mis. `students.view` untuk Data Mahasiswa),
     * sehingga operator tidak bisa mengunduh data di luar haknya lewat pintu ini.
     */
    public function dataset(Request $request, string $dataset): StreamedResponse|JsonResponse
    {
        $definition = $this->exports->datasetDefinition($dataset);

        abort_unless(
            $request->user()?->can($definition['permission']) ?? false,
            403,
            'Anda tidak memiliki hak untuk mengunduh '.$definition['label'].'.'
        );

        $required = array_values($definition['requires'] ?? []);
        $missing = array_filter($required, static fn (string $key) => blank($request->query($key)));

        if ($missing !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Lengkapi parameter berikut sebelum mengunduh: '.implode(', ', $missing).'.',
                'errors' => ['missing' => array_values($missing)],
            ], 422);
        }

        $data = $this->exports->datasetRows($definition, $request);

        $meta = $this->exports->meta($dataset, $this->filters($request, $definition)) + [
            'dataset_label' => $definition['label'],
            'source' => 'SIAKAD → data pelaporan PDDikti / Neo Feeder',
        ];

        return ExportDownload::stream(
            request: $request,
            dataset: $dataset,
            basename: $definition['basename'] ?? $dataset,
            headers: $data['headers'],
            rows: $data['rows'],
            meta: $meta,
            rowLimit: IntegratorExportService::MAX_ROWS,
            dataKeys: $data['keys'] ?? null,
        );
    }

    /**
     * @param  array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}  $data
     * @param  array<string, mixed>  $filters
     */
    private function download(
        Request $request,
        string $dataset,
        string $basename,
        array $data,
        array $filters,
    ): StreamedResponse {
        return ExportDownload::stream(
            request: $request,
            dataset: $dataset,
            basename: $basename,
            headers: $data['headers'],
            rows: $data['rows'],
            meta: $this->exports->meta($dataset, $filters),
            rowLimit: IntegratorExportService::MAX_ROWS,
            dataKeys: $data['keys'] ?? null,
        );
    }

    /**
     * Filter yang dipakai untuk meta berkas: hanya parameter yang relevan dengan dataset.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $definition): array
    {
        $allowed = array_values($definition['filters'] ?? []);

        return array_filter(
            Arr::only($request->query(), $allowed),
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function logFilters(Request $request): array
    {
        return array_filter([
            'api_client_id' => $request->query('api_client_id'),
            'api_key_id' => $request->query('api_key_id'),
            'method' => $request->query('method'),
            'status_code' => $request->query('status_code'),
            'successful' => $request->query('successful'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'search' => $request->query('search'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function format(Request $request): string
    {
        return strtolower((string) $request->query('format', 'csv')) === 'json' ? 'json' : 'csv';
    }
}
