<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\NeoFeeder\NeoFeederClient;
use Integrator\Siakad\SiakadClient;
use Integrator\Siakad\SiakadException;
use Integrator\Sync\Syncers\FeederReferencePuller;
use Integrator\Support\Database;
use RuntimeException;
use Throwable;

/**
 * Orchestrates one entity sync: pull from SIAKAD, map, push to Neo Feeder, log.
 *
 * Safety rules baked in here:
 * - a row that already has a mapping and an unchanged payload is skipped, so a
 *   repeated run never re-inserts national data;
 * - insert-only endpoints are never called twice for the same row;
 * - rows missing required feeder fields are skipped with an explicit message
 *   instead of being pushed half-empty;
 * - `dry-run` (the default) does everything except the actual feeder call;
 * - the run stops after a burst of failures, because 200 rejected rows usually
 *   mean one systematic problem (wrong id_prodi, locked period, ...).
 */
final class SyncRunner
{
    private const MAX_CONSECUTIVE_FAILURES = 200;

    public function __construct(
        private readonly SiakadClient $siakad,
        private readonly NeoFeederClient $feeder,
        private readonly Database $db,
        private readonly Registry $registry,
        private readonly MappingRepository $mappings,
        private readonly ReferenceResolver $references,
        private readonly SyncLogRepository $logRepository
    ) {
    }

    public function logs(): SyncLogRepository
    {
        return $this->logRepository;
    }

    public function run(string $entity, SyncOptions $options): RunResult
    {
        $syncer = $this->registry->get($entity);
        $runId = gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
        $startedAt = microtime(true);

        $this->db->insert('sync_runs', [
            'run_id' => $runId,
            'entity' => $entity,
            'mode' => $options->mode(),
            'semester_code' => $options->semesterCode,
            'started_at' => $this->db->now(),
        ]);

        $context = new SyncContext($runId, $entity, $options, $this->references, $this->mappings, $this->db);
        $result = new RunResult($runId, $entity, $options->mode());

        try {
            if ($syncer instanceof FeederReferencePuller) {
                $report = $syncer->pull($this->feeder, $this->references, $context);

                foreach ($report as $row) {
                    $result->total++;

                    match ($row['status']) {
                        'success' => $result->succeeded += max(1, $row['count']),
                        default => $result->failed++,
                    };
                }
            } else {
                $this->pullAndPush($syncer, $context, $options, $result);
            }
        } catch (SiakadException $exception) {
            $result->error = $exception->getMessage().' '.$exception->hint();
            $context->log('run', 'failed', null, null, $result->error);
        } catch (Throwable $exception) {
            $result->error = $exception->getMessage();
            $context->log('run', 'failed', null, null, $result->error);
        }

        $result->durationSeconds = round(microtime(true) - $startedAt, 2);

        $this->db->update('sync_runs', [
            'finished_at' => $this->db->now(),
            'total' => $result->total,
            'succeeded' => $result->succeeded,
            'failed' => $result->failed,
            'skipped' => $result->skipped,
            'planned' => $result->planned,
            'notes' => $result->error,
        ], ['run_id' => $runId]);

        return $result;
    }

    private function pullAndPush(
        SyncerInterface $syncer,
        SyncContext $context,
        SyncOptions $options,
        RunResult $result
    ): void {
        $endpoint = $syncer->sourceEndpoint();

        if ($endpoint === null) {
            throw new RuntimeException("Entity [{$syncer->key()}] tidak memiliki sumber SIAKAD.");
        }

        $options = $this->resolveSemester($syncer, $options, $context);
        $context = new SyncContext($context->runId, $syncer->key(), $options, $this->references, $this->mappings, $this->db);

        $consecutiveFailures = 0;

        $iteration = $this->siakad->each(
            $endpoint,
            $syncer->sourceQuery($options),
            function (array $row) use ($syncer, $context, $options, $result, &$consecutiveFailures): bool {
                foreach ($syncer->expand($row, $context) as $expanded) {
                    $result->total++;

                    $status = $this->pushRow($syncer, $expanded, $context, $options);

                    match ($status) {
                        'succeeded' => $result->succeeded += 1,
                        'planned' => $result->planned += 1,
                        'skipped' => $result->skipped += 1,
                        default => $result->failed += 1,
                    };

                    $consecutiveFailures = $status === 'failed' ? $consecutiveFailures + 1 : 0;

                    if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                        $context->log(
                            'run',
                            'failed',
                            null,
                            null,
                            'Dihentikan otomatis setelah '.self::MAX_CONSECUTIVE_FAILURES.' kegagalan berturut-turut.'
                        );

                        return false;
                    }

                    if ($context->limitReached($result->total)) {
                        return false;
                    }
                }

                return true;
            },
            $options->limit
        );

        $result->pages = $iteration['pages'];
    }

    /**
     * Push (or simulate) one expanded row.
     *
     * @param  array<string, mixed>  $row
     * @return string succeeded|failed|skipped|planned
     */
    private function pushRow(
        SyncerInterface $syncer,
        array $row,
        SyncContext $context,
        SyncOptions $options
    ): string {
        $localKey = $syncer->localKey($row);

        if ($localKey === '') {
            $context->log('mapping', 'skipped', null, null, 'Kunci lokal tidak dapat dibentuk dari baris sumber.');

            return 'skipped';
        }

        if ($options->onlyLocalKey !== null && $options->onlyLocalKey !== $localKey) {
            return 'skipped';
        }

        $mapping = $context->mapping($syncer->key(), $localKey);
        $payload = $syncer->payload($row, $context, $mapping !== null);

        $missing = [];
        foreach ($syncer->requiredFields() as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            $context->log(
                'validate',
                'skipped',
                $localKey,
                null,
                'Kolom wajib belum lengkap: '.implode(', ', $missing),
                ['payload' => $payload]
            );

            return 'skipped';
        }

        $act = $mapping === null ? $syncer->actInsert() : $syncer->actUpdate();

        if ($act === null) {
            $context->log('skip', 'skipped', $localKey, $mapping['feeder_id'] ?? null, 'Sudah tersinkron (endpoint hanya mendukung insert).');

            return 'skipped';
        }

        $hash = $syncer->payloadHash($row, $context, $payload);

        if ($mapping !== null && ! $options->force && ($mapping['payload_hash'] ?? null) === $hash) {
            $context->log('unchanged', 'skipped', $localKey, $mapping['feeder_id'] ?? null, 'Tidak ada perubahan sejak sinkronisasi terakhir.');

            return 'skipped';
        }

        if ($options->dryRun) {
            $context->log('dry-run', 'planned', $localKey, $mapping['feeder_id'] ?? null, "Akan memanggil {$act}.", ['payload' => $payload]);

            return 'planned';
        }

        try {
            $response = $this->feeder->call($act, $payload);
        } catch (Throwable $exception) {
            $context->log('push', 'failed', $localKey, $mapping['feeder_id'] ?? null, $exception->getMessage(), ['payload' => $payload]);

            return 'failed';
        }

        if (! $response->isOk()) {
            $context->log(
                'push',
                'failed',
                $localKey,
                $mapping['feeder_id'] ?? null,
                $response->message(),
                ['payload' => $payload, 'response' => $response->payload()]
            );

            return 'failed';
        }

        $feederId = $response->createdId() ?? ($mapping['feeder_id'] ?? null);

        $context->mappings->remember($syncer->key(), $localKey, $feederId === null ? null : (string) $feederId, $hash);

        $context->log('push', 'succeeded', $localKey, $feederId, "{$act} berhasil.", ['response' => $response->summary(200)]);

        return 'succeeded';
    }

    /**
     * A semester-scoped entity needs both the SIAKAD id (for the pull) and the
     * PDDikti code (for the payload). A code supplied on its own is resolved here.
     */
    private function resolveSemester(SyncerInterface $syncer, SyncOptions $options, SyncContext $context): SyncOptions
    {
        if (! $syncer->requiresSemester()) {
            return $options;
        }

        if ($options->semesterId !== null) {
            return $options;
        }

        if ($options->semesterCode === null) {
            throw new RuntimeException('Entity ini memerlukan semester. Pilih semester pada form sinkronisasi.');
        }

        $resolved = null;

        foreach ($this->siakad->semesters() as $semester) {
            if ((string) ($semester['feeder_code'] ?? '') === $options->semesterCode) {
                $resolved = $semester;

                break;
            }
        }

        if ($resolved === null) {
            throw new RuntimeException("Semester dengan kode feeder {$options->semesterCode} tidak ditemukan di SIAKAD.");
        }

        $context->log('resolve', 'succeeded', null, (string) $resolved['id'], "Semester {$options->semesterCode} dipetakan ke id SIAKAD {$resolved['id']}.");

        return new SyncOptions(
            dryRun: $options->dryRun,
            semesterCode: $options->semesterCode,
            semesterId: (int) $resolved['id'],
            limit: $options->limit,
            force: $options->force,
            onlyLocalKey: $options->onlyLocalKey
        );
    }
}
