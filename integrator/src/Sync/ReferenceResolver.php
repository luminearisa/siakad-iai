<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\Support\Database;

/**
 * Resolves the identifiers Neo Feeder expects (id_prodi, id_semester, ...) from the
 * reference data pulled out of the feeder itself.
 *
 * PDDikti identifiers are not stable across installations, so they are never
 * hardcoded: the Reference syncer stores them and everything else looks them up.
 */
final class ReferenceResolver
{
    public function __construct(private readonly Database $db)
    {
    }

    public function find(string $kind, string $referenceKey): ?array
    {
        return $this->db->first(
            'SELECT * FROM feeder_reference WHERE kind = :kind AND reference_key = :key',
            ['kind' => $kind, 'key' => $referenceKey]
        );
    }

    /**
     * Feeder id for a reference row (id_prodi, id_semester, ...).
     */
    public function feederId(string $kind, string $referenceKey): ?string
    {
        $row = $this->find($kind, $referenceKey);

        if ($row === null) {
            return null;
        }

        $id = $row['feeder_id'] ?? null;

        return $id === null || $id === '' ? null : (string) $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(string $kind, string $referenceKey, ?string $feederId, array $payload): void
    {
        $this->db->upsert('feeder_reference', [
            'kind' => $kind,
            'reference_key' => $referenceKey,
            'feeder_id' => $feederId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'synced_at' => $this->db->now(),
        ], ['kind', 'reference_key'], ['feeder_id', 'payload', 'synced_at']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $kind, int $limit = 500): array
    {
        $rows = $this->db->select(
            'SELECT * FROM feeder_reference WHERE kind = :kind ORDER BY reference_key LIMIT :limit',
            ['kind' => $kind, 'limit' => $limit]
        );

        return array_map(static function (array $row) {
            $row['payload'] = json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];

            return $row;
        }, $rows);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $rows = $this->db->select('SELECT kind, COUNT(*) AS total FROM feeder_reference GROUP BY kind');
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['kind']] = (int) $row['total'];
        }

        return $counts;
    }

    public function isFresh(int $seconds = 86400): bool
    {
        $latest = $this->db->scalar('SELECT MAX(synced_at) FROM feeder_reference');

        if (! is_string($latest) || $latest === '') {
            return false;
        }

        return (time() - strtotime($latest)) < $seconds;
    }
}
