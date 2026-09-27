<?php

namespace Modules\Integrator\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyalur berkas unduhan untuk fitur "Export as…" di halaman SIAKAD.
 *
 * Dipakai bersama oleh unduhan data integrasi (log/klien/kunci) dan unduhan data
 * pelaporan feeder (mahasiswa, kelas, KRS, nilai, ...). Semua berkas memakai aturan
 * yang sama:
 *
 *  - CSV UTF-8 dengan BOM supaya langsung rapi saat dibuka di Excel;
 *  - nilai berbahaya diberi kutip di depan (anti CSV injection);
 *  - baris ditulis mengalir (`streamDownload`) agar data besar tidak menumpuk di memori;
 *  - setiap unduhan dicatat pada log aplikasi sebagai jejak audit.
 */
final class ExportDownload
{
    /**
     * @param  array<int, string>  $headers  Judul kolom (atau nama field mentah).
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<string, mixed>  $meta  Ikut ditulis ke JSON dan ke log audit.
     * @param  array<int, string>|null  $dataKeys  Nama field untuk wadah JSON; bila kosong
     *                                             memakai `$headers`.
     */
    public static function stream(
        Request $request,
        string $dataset,
        string $basename,
        array $headers,
        iterable $rows,
        array $meta,
        int $rowLimit,
        ?array $dataKeys = null,
    ): StreamedResponse {
        $format = self::format($request);

        $meta = $meta + [
            'format' => $format,
            'generated_by' => $request->user()?->only(['id', 'name', 'email']),
        ];

        $filename = $basename.'-'.now()->format('Ymd-His').'.'.$format;

        // Jejak audit: siapa mengunduh data apa, kapan, dan dengan filter apa.
        Log::info('integrator.export', $meta);

        return response()->streamDownload(
            function () use ($headers, $rows, $format, $meta, $dataKeys): void {
                $out = fopen('php://output', 'wb');

                if ($out === false) {
                    return;
                }

                $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

                if ($format === 'json') {
                    fwrite($out, '{"meta":'.json_encode($meta, $flags).',"headers":'.json_encode($headers, $flags).',"data":[');

                    $count = 0;

                    $keys = $dataKeys ?? $headers;

                    foreach ($rows as $row) {
                        $record = count($row) === count($keys) ? array_combine($keys, $row) : array_values($row);

                        fwrite($out, ($count === 0 ? '' : ',').json_encode($record, $flags));
                        $count++;
                    }

                    fwrite($out, '],"rows":'.$count.'}');
                } else {
                    // BOM UTF-8 agar Excel (khususnya versi Windows) tidak merusak aksen.
                    fwrite($out, "\xEF\xBB\xBF");

                    fputcsv($out, $headers, ',', '"', '');

                    foreach ($rows as $row) {
                        fputcsv($out, array_map(self::csvCell(...), $row), ',', '"', '');
                    }
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => $format === 'json' ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
                'X-Export-Row-Limit' => (string) $rowLimit,
                'X-Export-Dataset' => $dataset,
            ],
        );
    }

    /**
     * Nilai sel CSV yang aman dibuka di Excel: tanda kutip di depan untuk teks yang
     * diawali karakter formula, sehingga tidak ada data pengguna yang dieksekusi
     * sebagai rumus (CSV injection).
     */
    public static function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof \UnitEnum) {
            $value = $value->name;
        }

        $text = is_scalar($value)
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($text !== '' && ! is_numeric($text) && preg_match('/^[=+\-@\t\r]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }

    /**
     * `?format=csv` (bawaan) atau `?format=json`.
     */
    public static function format(Request $request): string
    {
        return strtolower((string) $request->query('format', 'csv')) === 'json' ? 'json' : 'csv';
    }
}
