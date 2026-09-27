<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Support\View;
use Integrator\Web\Response;

/**
 * Ledger of SIAKAD → feeder identifiers, with manual repair.
 *
 * Two situations need this screen: someone re-created a row in Neo Feeder by hand, or
 * a mapping points at the wrong record and must be corrected without a SQL client.
 */
final class MappingController
{
    private const PER_PAGE = 50;

    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $entity = is_string($_GET['entity'] ?? null) ? trim((string) $_GET['entity']) : 'students';
        $search = is_string($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $rows = $this->app->mappings()->forEntity($entity, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
                return stripos((string) $row['local_key'], $search) !== false
                    || stripos((string) ($row['feeder_id'] ?? ''), $search) !== false;
            }));
        }

        return Response::html($this->app->view()->page('mappings/index', [
            'title' => 'Pemetaan ID',
            'entity' => $entity,
            'search' => $search,
            'page' => $page,
            'rows' => $rows,
            'counts' => $this->app->mappings()->countsByEntity(),
            'entities' => array_keys($this->app->registry()->all()),
        ]));
    }

    public function update(): Response
    {
        $entity = trim((string) ($_POST['entity'] ?? ''));
        $localKey = trim((string) ($_POST['local_key'] ?? ''));
        $feederId = trim((string) ($_POST['feeder_id'] ?? ''));

        if ($entity === '' || $localKey === '') {
            View::flash('error', 'Entity dan kunci lokal wajib diisi.');

            return Response::redirect('/mappings');
        }

        $existing = $this->app->mappings()->find($entity, $localKey);

        if ($existing === null) {
            View::flash('error', 'Pemetaan tidak ditemukan: '.$localKey);

            return Response::redirect('/mappings?entity='.urlencode($entity));
        }

        $this->app->mappings()->remember($entity, $localKey, $feederId === '' ? null : $feederId, null);

        View::flash('success', 'Pemetaan '.$localKey.' diperbarui.');

        return Response::redirect('/mappings?entity='.urlencode($entity));
    }

    public function delete(): Response
    {
        $entity = trim((string) ($_POST['entity'] ?? ''));
        $localKey = trim((string) ($_POST['local_key'] ?? ''));

        if ($entity === '' || $localKey === '') {
            View::flash('error', 'Entity dan kunci lokal wajib diisi.');

            return Response::redirect('/mappings');
        }

        $this->app->mappings()->forget($entity, $localKey);

        View::flash('success', 'Pemetaan dihapus. Baris akan dikirim ulang pada sinkronisasi berikutnya.');

        return Response::redirect('/mappings?entity='.urlencode($entity));
    }
}
