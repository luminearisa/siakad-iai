<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\Support\Database;

/**
 * Ledger of which SIAKAD row already exists in Neo Feeder.
 *
 * Without this table every run would re-insert students and classes, which is the
 * single easiest way to corrupt PDDikti data. Keys are `entity + local key`
 * (for example `students` + `nim:202501001`).
 */
final class MappingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $entity, string $localKey): ?array
    {
        return $this->db->first(
            'SELECT * FROM mappings WHERE entity = :entity AND local_key = :local_key',
            ['entity' => $entity, 'local_key' => $localKey]
        );
    }

    public function feederId(string $entity, string $localKey): ?string
    {
        $row = $this->find($entity, $localKey);

        return $row === null ? null : (string) ($row['feeder_id'] ?? '');
    }

    public function remember(string $entity, string $localKey, ?string $feederId, ?string $payloadHash = null): void
    {
        $now = $this->db->now();
        $existing = $this->find($entity, $localKey);

        if ($existing === null) {
            $this->db->insert('mappings', [
                'entity' => $entity,
                'local_key' => $localKey,
                'feeder_id' => $feederId,
                'payload_hash' => $payloadHash,
                'first_synced_at' => $now,
                'last_synced_at' => $now,
            ]);

            return;
        }

        $this->db->update('mappings', [
            'feeder_id' => $feederId ?? ($existing['feeder_id'] ?? null),
            'payload_hash' => $payloadHash,
            'last_synced_at' => $now,
        ], ['id' => (int) $existing['id']]);
    }

    public function forget(string $entity, string $localKey): void
    {
        $this->db->execute(
            'DELETE FROM mappings WHERE entity = :entity AND local_key = :local_key',
            ['entity' => $entity, 'local_key' => $localKey]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forEntity(string $entity, int $limit = 200, int $offset = 0): array
    {
        return $this->db->select(
            'SELECT * FROM mappings WHERE entity = :entity ORDER BY last_synced_at DESC LIMIT :limit OFFSET :offset',
            ['entity' => $entity, 'limit' => $limit, 'offset' => $offset]
        );
    }

    /**
     * Row counts per entity, for the dashboard.
     *
     * @return array<string, int>
     */
    public function countsByEntity(): array
    {
        $rows = $this->db->select('SELECT entity, COUNT(*) AS total FROM mappings GROUP BY entity');
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['entity']] = (int) $row['total'];
        }

        return $counts;
    }
}
