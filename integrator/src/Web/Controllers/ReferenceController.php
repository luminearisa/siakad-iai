<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Support\View;
use Integrator\Web\Response;

/**
 * Feeder reference data pulled locally: PT, prodi, periode, dosen, kategori
 * kegiatan, and the column dictionary used to verify field names.
 */
final class ReferenceController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $kind = is_string($_GET['kind'] ?? null) && trim((string) $_GET['kind']) !== ''
            ? trim((string) $_GET['kind'])
            : 'prodi';

        $search = is_string($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
        $rows = $this->app->references()->all($kind, 1000);

        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
                return stripos((string) $row['reference_key'], $search) !== false
                    || stripos((string) ($row['feeder_id'] ?? ''), $search) !== false
                    || stripos((string) json_encode($row['payload'] ?? []), $search) !== false;
            }));
        }

        return Response::html($this->app->view()->page('reference/index', [
            'title' => 'Referensi Neo Feeder',
            'kind' => $kind,
            'search' => $search,
            'rows' => array_slice($rows, 0, 300),
            'total' => count($rows),
            'counts' => $this->app->references()->counts(),
            'kinds' => [
                'pt' => 'Profil PT',
                'prodi' => 'Program Studi',
                'periode' => 'Periode / Semester',
                'dosen' => 'Dosen',
                'kategori_kegiatan' => 'Kategori Kegiatan',
                'dictionary' => 'Kamus Kolom',
            ],
            'fresh' => $this->app->references()->isFresh(),
        ]));
    }

    /**
     * Rename the local key of a reference row.
     *
     * Needed when the study program code in SIAKAD differs from the PDDikti code: the
     * operator types the PDDikti code here once instead of maintaining a hardcoded map.
     */
    public function rename(): Response
    {
        $kind = trim((string) ($_POST['kind'] ?? ''));
        $currentKey = trim((string) ($_POST['reference_key'] ?? ''));
        $newKey = trim((string) ($_POST['new_key'] ?? ''));

        if ($kind === '' || $currentKey === '' || $newKey === '') {
            View::flash('error', 'Kunci referensi lama dan baru wajib diisi.');

            return Response::redirect('/reference?kind='.urlencode($kind));
        }

        $row = $this->app->references()->find($kind, $currentKey);

        if ($row === null) {
            View::flash('error', 'Baris referensi tidak ditemukan.');

            return Response::redirect('/reference?kind='.urlencode($kind));
        }

        $payload = json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
        $this->app->references()->store($kind, $newKey, $row['feeder_id'] === null ? null : (string) $row['feeder_id'], $payload);

        View::flash('success', 'Kunci referensi '.$currentKey.' dipetakan ulang menjadi '.$newKey.' (baris lama tetap tersimpan).');

        return Response::redirect('/reference?kind='.urlencode($kind).'&search='.urlencode($newKey));
    }
}
