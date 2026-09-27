<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Integrator\Services\IntegratorExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan ("Export as…") untuk data yang berkaitan dengan pelaporan PDDikti /
 * Neo Feeder: log akses integrasi, rekap harian per klien, daftar klien, dan
 * daftar kunci API.
 *
 * Format:
 *  - `?format=csv` (bawaan) — CSV UTF-8 dengan BOM supaya langsung rapi di Excel;
 *  - `?format=json` — arsip terstruktur (meta + headers + data) untuk tim integrator.
 *
 * Unduhan dibatasi {@see IntegratorExportService::MAX_ROWS} baris per berkas dan
 * setiap unduhan dicatat ke log aplikasi sebagai jejak audit.
 */
class IntegratorExportController extends Controller
{
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
        $format = $this->format($request);
        $headers = $data['headers'];
        $rows = $data['rows'];
        $meta = $this->exports->meta($dataset, $filters) + [
            'format' => $format,
            'generated_by' => $request->user()?->only(['id', 'name', 'email']),
        ];

        $filename = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        // Jejak audit: siapa mengunduh data integrasi PDDikti, kapan, dan dengan filter apa.
        Log::info('integrator.export', $meta);

        return response()->streamDownload(
            function () use ($headers, $rows, $format, $meta): void {
                $out = fopen('php://output', 'wb');

                if ($out === false) {
                    return;
                }

                $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

                if ($format === 'json') {
                    fwrite($out, '{"meta":'.json_encode($meta, $flags).',"headers":'.json_encode($headers, $flags).',"data":[');

                    $count = 0;

                    foreach ($rows as $row) {
                        $record = count($row) === count($headers) ? array_combine($headers, $row) : array_values($row);

                        fwrite($out, ($count === 0 ? '' : ',').json_encode($record, $flags));
                        $count++;
                    }

                    fwrite($out, '],"rows":'.$count.'}');
                } else {
                    // BOM UTF-8 agar Excel (khususnya versi Windows) tidak merusak aksen.
                    fwrite($out, "\xEF\xBB\xBF");

                    fputcsv($out, $headers, ',', '"', '');

                    foreach ($rows as $row) {
                        fputcsv($out, array_map($this->csvCell(...), $row), ',', '"', '');
                    }
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => $format === 'json' ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
                'X-Export-Row-Limit' => (string) IntegratorExportService::MAX_ROWS,
                'X-Export-Dataset' => $dataset,
            ],
        );
    }

    /**
     * Nilai sel CSV yang aman dibuka di Excel: tanda kutip di depan untuk teks yang
     * diawali karakter formula, sehingga tidak ada data pengguna yang dieksekusi
     * sebagai rumus (CSV injection).
     */
    private function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($text !== '' && ! is_numeric($text) && preg_match('/^[=+\-@\t\r]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
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
