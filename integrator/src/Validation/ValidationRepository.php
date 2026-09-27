<?php

declare(strict_types=1);

namespace Integrator\Validation;

use Integrator\Support\Database;

/**
 * Penyimpanan hasil validasi: riwayat pemeriksaan, temuan per baris, dan
 * snapshot cakupan pelaporan (persentase data yang sudah terkirim).
 */
final class ValidationRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * @param  array<int, string>  $entities
     */
    public function startRun(string $scope, ?string $semester, array $entities): string
    {
        $runId = gmdate('YmdHis').'-'.bin2hex(random_bytes(4));

        $this->db->insert('validation_runs', [
            'run_id' => $runId,
            'scope' => $scope,
            'semester' => $semester,
            'entities' => json_encode(array_values($entities), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => 'running',
            'started_at' => $this->db->now(),
        ]);

        return $runId;
    }

    /**
     * @param  array<int, Violation>  $violations
     */
    public function saveFindings(string $runId, array $violations): int
    {
        if ($violations === []) {
            return 0;
        }

        $saved = 0;
        $now = $this->db->now();

        $this->db->transaction(function () use ($runId, $violations, $now, &$saved): void {
            foreach ($violations as $violation) {
                $row = $violation->toArray();

                $this->db->insert('validation_findings', [
                    'run_id' => $runId,
                    'entity' => $row['entity'],
                    'local_key' => $row['local_key'],
                    'field' => $row['field'],
                    'code' => $row['code'],
                    'severity' => $row['severity'],
                    'message' => $row['message'],
                    'hint' => $row['hint'],
                    'prodi' => $row['prodi'],
                    'period' => $row['period'],
                    'entity_level' => $row['entity_level'] ? 1 : 0,
                    'created_at' => $now,
                ]);

                $saved++;
            }
        });

        return $saved;
    }

    /**
     * Simpan snapshot cakupan. Satu baris per (entity, periode, prodi) —
     * pemeriksaan berikutnya memperbarui baris yang sama.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveCoverage(string $runId, array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $this->db->upsert('coverage_stats', [
                'run_id' => $runId,
                'entity' => (string) $row['entity'],
                'period' => (string) ($row['period'] ?? '-'),
                'prodi' => (string) ($row['prodi'] ?? '-'),
                'prodi_label' => $row['prodi_label'] ?? null,
                'total' => (int) ($row['total'] ?? 0),
                'valid' => (int) ($row['valid'] ?? 0),
                'invalid' => (int) ($row['invalid'] ?? 0),
                'warnings' => (int) ($row['warnings'] ?? 0),
                'synced' => (int) ($row['synced'] ?? 0),
                'pending' => (int) ($row['pending'] ?? 0),
                'stale' => (int) ($row['stale'] ?? 0),
                'percentage' => round((float) ($row['percentage'] ?? 0), 2),
                'updated_at' => $this->db->now(),
            ], ['entity', 'period', 'prodi']);

            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    public function finishRun(string $runId, array $totals, string $status = 'finished', ?string $message = null): void
    {
        $this->db->update('validation_runs', [
            'status' => $status,
            'total_records' => (int) ($totals['total'] ?? 0),
            'total_valid' => (int) ($totals['valid'] ?? 0),
            'total_invalid' => (int) ($totals['invalid'] ?? 0),
            'total_findings' => (int) ($totals['findings'] ?? 0),
            'total_synced' => (int) ($totals['synced'] ?? 0),
            'percentage' => round((float) ($totals['percentage'] ?? 0), 2),
            'message' => $message,
            'finished_at' => $this->db->now(),
        ], ['run_id' => $runId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestRun(): ?array
    {
        return $this->db->first('SELECT * FROM validation_runs ORDER BY id DESC LIMIT 1');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runs(int $limit = 20): array
    {
        return $this->db->select('SELECT * FROM validation_runs ORDER BY id DESC LIMIT :limit', ['limit' => $limit]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function findings(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $parameters] = $this->buildWhere($filters);

        $parameters['limit'] = $limit;
        $parameters['offset'] = $offset;

        return $this->db->select(
            "SELECT * FROM validation_findings {$where} ORDER BY CASE severity WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END, id ASC LIMIT :limit OFFSET :offset",
            $parameters
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function countFindings(array $filters = []): int
    {
        [$where, $parameters] = $this->buildWhere($filters);

        return (int) $this->db->scalar("SELECT COUNT(*) FROM validation_findings {$where}", $parameters);
    }

    /**
     * Rekap alasan tidak valid (mirip "rekap data tidak valid" pada ProFeeder).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findingsByCode(?string $runId = null, int $limit = 15): array
    {
        [$where, $parameters] = $this->buildWhere(['run_id' => $runId, 'severity' => Violation::ERROR]);

        $parameters['limit'] = $limit;

        return $this->db->select(
            "SELECT code, entity, COUNT(*) AS total, MIN(message) AS message, MIN(hint) AS hint
             FROM validation_findings {$where}
             GROUP BY code, entity ORDER BY total DESC LIMIT :limit",
            $parameters
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findingsByEntity(?string $runId = null): array
    {
        [$where, $parameters] = $this->buildWhere(['run_id' => $runId]);

        return $this->db->select(
            "SELECT entity, severity, COUNT(*) AS total FROM validation_findings {$where} GROUP BY entity, severity ORDER BY entity ASC",
            $parameters
        );
    }

    /**
     * Cakupan terakhir per entity + prodi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function coverage(?string $semester = null): array
    {
        $runId = $this->latestRun()['run_id'] ?? null;

        if ($runId === null) {
            return [];
        }

        $parameters = ['run_id' => $runId];
        $semesterClause = '';

        if ($semester !== null && $semester !== '') {
            $semesterClause = ' AND period = :period';
            $parameters['period'] = $semester;
        }

        return $this->db->select(
            "SELECT * FROM coverage_stats WHERE run_id = :run_id{$semesterClause} ORDER BY entity ASC, prodi ASC",
            $parameters
        );
    }

    /**
     * Cakupan per program studi (semua entity digabung) — inti fitur
     * "persentase pelaporan per prodi".
     *
     * @return array<int, array<string, mixed>>
     */
    public function coverageByProdi(?string $semester = null, int $limit = 25): array
    {
        $runId = $this->latestRun()['run_id'] ?? null;

        if ($runId === null) {
            return [];
        }

        $parameters = ['run_id' => $runId, 'limit' => $limit];
        $semesterClause = '';

        if ($semester !== null && $semester !== '') {
            $semesterClause = ' AND period = :period';
            $parameters['period'] = $semester;
        }

        return $this->db->select(
            "SELECT prodi, MAX(prodi_label) AS prodi_label,
                    SUM(total) AS total, SUM(valid) AS valid, SUM(invalid) AS invalid,
                    SUM(warnings) AS warnings, SUM(synced) AS synced, SUM(pending) AS pending, SUM(stale) AS stale
             FROM coverage_stats
             WHERE run_id = :run_id{$semesterClause}
             GROUP BY prodi
             ORDER BY total DESC
             LIMIT :limit",
            $parameters
        );
    }

    /**
     * Ringkasan cakupan per entity (menjumlahkan baris per prodi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function coverageByEntity(?string $semester = null): array
    {
        $rows = [];
        $current = null;

        foreach ($this->coverage($semester) as $row) {
            $entity = (string) $row['entity'];

            if ($current === null || $current['entity'] !== $entity) {
                if ($current !== null) {
                    $rows[] = $this->finaliseCoverage($current);
                }

                $current = [
                    'entity' => $entity,
                    'period' => $row['period'],
                    'total' => 0,
                    'valid' => 0,
                    'invalid' => 0,
                    'warnings' => 0,
                    'synced' => 0,
                    'pending' => 0,
                    'stale' => 0,
                ];
            }

            foreach (['total', 'valid', 'invalid', 'warnings', 'synced', 'pending', 'stale'] as $key) {
                $current[$key] += (int) ($row[$key] ?? 0);
            }
        }

        if ($current !== null) {
            $rows[] = $this->finaliseCoverage($current);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function coverageTotals(?string $semester = null): array
    {
        $totals = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'warnings' => 0, 'synced' => 0, 'pending' => 0, 'stale' => 0, 'percentage' => 0.0];

        foreach ($this->coverage($semester) as $row) {
            foreach (['total', 'valid', 'invalid', 'warnings', 'synced', 'pending', 'stale'] as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }

        $totals['percentage'] = $totals['total'] > 0 ? round($totals['synced'] / $totals['total'] * 100, 2) : 0.0;

        return $totals;
    }

    /**
     * Hapus riwayat validasi lama agar database tetap ringan.
     */
    public function purge(int $days = 30): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
        $removed = 0;

        $runs = $this->db->select('SELECT run_id FROM validation_runs WHERE started_at < :cutoff', ['cutoff' => $cutoff]);

        foreach ($runs as $run) {
            $this->db->execute('DELETE FROM validation_findings WHERE run_id = :run_id', ['run_id' => $run['run_id']]);
            $removed++;
        }

        if ($removed > 0) {
            $this->db->execute('DELETE FROM validation_runs WHERE started_at < :cutoff', ['cutoff' => $cutoff]);
        }

        return $removed;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function finaliseCoverage(array $row): array
    {
        $row['percentage'] = $row['total'] > 0 ? round($row['synced'] / $row['total'] * 100, 2) : 0.0;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        $runId = $filters['run_id'] ?? null;

        if ($runId === null) {
            $latest = $this->latestRun();

            if ($latest !== null) {
                $runId = $latest['run_id'];
            }
        }

        if (is_string($runId) && $runId !== '') {
            $clauses[] = 'run_id = :run_id';
            $parameters['run_id'] = $runId;
        } elseif (array_key_exists('run_id', $filters)) {
            // Pemanggil meminta hasil lintas run (mis. rekap gabungan).
            $clauses[] = '1 = 1';
        }

        if (! empty($filters['entity']) && is_string($filters['entity'])) {
            $clauses[] = 'entity = :entity';
            $parameters['entity'] = $filters['entity'];
        }

        if (! empty($filters['severity']) && is_string($filters['severity'])) {
            $clauses[] = 'severity = :severity';
            $parameters['severity'] = $filters['severity'];
        }

        if (! empty($filters['code']) && is_string($filters['code'])) {
            $clauses[] = 'code = :code';
            $parameters['code'] = $filters['code'];
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $clauses[] = '(local_key LIKE :search OR message LIKE :search OR hint LIKE :search)';
            $parameters['search'] = '%'.$filters['search'].'%';
        }

        return [$clauses === [] ? '' : 'WHERE '.implode(' AND ', $clauses), $parameters];
    }
}
