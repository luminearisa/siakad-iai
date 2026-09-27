<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\Support\Database;

/**
 * Shared state for one sync run: options, id resolution and the audit trail.
 */
final class SyncContext
{
    public function __construct(
        public readonly string $runId,
        public readonly string $entity,
        public readonly SyncOptions $options,
        public readonly ReferenceResolver $references,
        public readonly MappingRepository $mappings,
        private readonly Database $db
    ) {
    }

    public function feederId(string $kind, ?string $referenceKey): ?string
    {
        if ($referenceKey === null || $referenceKey === '') {
            return null;
        }

        return $this->references->feederId($kind, $referenceKey);
    }

    /**
     * Feeder id of a semester, resolved from the PDDikti code (`20251`).
     */
    public function semesterId(?string $code = null): ?string
    {
        $code ??= $this->options->semesterCode;

        return $code === null ? null : $this->feederId('periode', $code);
    }

    /**
     * Mapping row for a SIAKAD row, if it was synced before.
     *
     * @return array<string, mixed>|null
     */
    public function mapping(string $entity, string $localKey): ?array
    {
        return $this->mappings->find($entity, $localKey);
    }

    public function limitReached(int $processed): bool
    {
        return $this->options->limit > 0 && $processed >= $this->options->limit;
    }

    /**
     * Append a log row for this run.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, string $status, ?string $localKey, ?string $feederId, ?string $message, array $context = []): void
    {
        $this->db->insert('sync_logs', [
            'run_id' => $this->runId,
            'entity' => $this->entity,
            'action' => $action,
            'status' => $status,
            'local_key' => $localKey,
            'feeder_id' => $feederId,
            'message' => $message,
            'response' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $this->db->now(),
        ]);
    }
}
