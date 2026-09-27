<?php

declare(strict_types=1);

namespace Integrator\Web\Controllers;

use Integrator\App;
use Integrator\Web\Response;

/**
 * Access log of everything the integrator pushed (or declined to push).
 */
final class LogController
{
    private const PER_PAGE = 50;

    public function __construct(private readonly App $app)
    {
    }

    public function index(): Response
    {
        $filters = [
            'entity' => $this->string('entity'),
            'status' => $this->string('status'),
            'search' => $this->string('search'),
            'run_id' => $this->string('run_id'),
        ];

        $filters = array_filter($filters, static fn (?string $value) => $value !== null && $value !== '');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->app->logRepository()->countLogs($filters);

        return Response::html($this->app->view()->page('logs/index', [
            'title' => 'Log Sinkronisasi',
            'filters' => $filters,
            'logs' => $this->app->logRepository()->logs($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'entities' => array_keys($this->app->registry()->all()),
            'statusCounts' => $this->app->logRepository()->totalsByStatus(),
        ]));
    }

    private function string(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
