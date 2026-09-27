<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\Support\Database;

/**
 * Reads the sync ledger for the UI (runs, per-row results, counters).
 */
final class SyncLogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runs(int $limit = 25): array
    {
        return $this->db->select(
            'SELECT * FROM sync_runs ORDER BY id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function run(string $runId): ?array
    {
        return $this->db->first('SELECT * FROM sync_runs WHERE run_id = :run_id', ['run_id' => $runId]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function logs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $parameters] = $this->buildWhere($filters);

        $parameters['limit'] = $limit;
        $parameters['offset'] = $offset;

        return $this->db->select(
            "SELECT * FROM sync_logs {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset",
            $parameters
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function countLogs(array $filters = []): int
    {
        [$where, $parameters] = $this->buildWhere($filters);

        return (int) $this->db->scalar("SELECT COUNT(*) FROM sync_logs {$where}", $parameters);
    }

    /**
     * Status counters for a run, used to render the result panel.
     *
     * @return array<string, int>
     */
    public function statusCounts(string $runId): array
    {
        $rows = $this->db->select(
            'SELECT status, COUNT(*) AS total FROM sync_logs WHERE run_id = :run_id GROUP BY status',
            ['run_id' => $runId]
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Latest failures grouped by message, to spot the recurring blocker quickly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topFailures(int $limit = 10): array
    {
        return $this->db->select(
            "SELECT entity, message, COUNT(*) AS total, MAX(created_at) AS last_seen
             FROM sync_logs
             WHERE status = 'failed'
             GROUP BY entity, message
             ORDER BY total DESC, last_seen DESC
             LIMIT :limit",
            ['limit' => $limit]
        );
    }

    /**
     * @return array<string, int>
     */
    public function totalsByStatus(): array
    {
        $rows = $this->db->select('SELECT status, COUNT(*) AS total FROM sync_logs GROUP BY status');
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function totalsByEntity(): array
    {
        $rows = $this->db->select('SELECT entity, COUNT(*) AS total FROM sync_logs GROUP BY entity');
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['entity']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['entity', 'status', 'run_id', 'action'] as $column) {
            if (! empty($filters[$column]) && is_string($filters[$column])) {
                $conditions[] = "{$column} = :{$column}";
                $parameters[$column] = $filters[$column];
            }
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $conditions[] = '(local_key LIKE :search OR message LIKE :search OR feeder_id LIKE :search)';
            $parameters['search'] = '%'.$filters['search'].'%';
        }

        return [
            $conditions === [] ? '' : 'WHERE '.implode(' AND ', $conditions),
            $parameters,
        ];
    }
}
