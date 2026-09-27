<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\NeoFeeder\ErrorCatalog;
use Integrator\NeoFeeder\NeoFeederClient;
use Integrator\Siakad\SiakadClient;
use Integrator\Siakad\SiakadException;
use Integrator\Sync\Syncers\FeederReferencePuller;
use Integrator\Validation\FeederValidator;
use Integrator\Support\Database;
use Integrator\Support\Settings;
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
 * - setiap baris divalidasi lebih dulu dengan aturan Neo Feeder (`config/feeder_rules.php`):
 *   baris yang pasti ditolak feeder dicatat sebagai `invalid` dan TIDAK dikirim;
 * - pesan error feeder diterjemahkan katalog error menjadi kategori + langkah
 *   penanganan, sehingga operator tahu apa yang harus diperbaiki;
 * - jeda antar baris bisa diatur (`request_delay_ms`) agar server feeder yang
 *   sedang sibuk tidak dihujani permintaan;
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
        private readonly SyncLogRepository $logRepository,
        private readonly ?FeederValidator $validator = null,
        private readonly ?ErrorCatalog $errorCatalog = null,
        private readonly ?Settings $settings = null
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
            'invalid' => $result->invalid,
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
                        'invalid' => $result->invalid += 1,
                        default => $result->failed += 1,
                    };

                    $consecutiveFailures = in_array($status, ['failed', 'invalid'], true) ? $consecutiveFailures + 1 : 0;

                    if (! $options->dryRun) {
                        $this->throttle();
                    }

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

        if ($this->shouldValidate($options)) {
            $violations = $this->validator->validateRow($syncer->key(), $localKey, $payload, $row, $options->semesterCode);
            $errors = array_values(array_filter($violations, static fn ($violation) => $violation->isError()));

            if ($errors !== []) {
                $first = $errors[0];
                $category = $this->errorCatalog?->categoryOf($first->message) ?? 'validasi_data';
                $hint = $first->hint ?? $this->errorCatalog?->hintFor($first->message);

                $context->log(
                    'validate',
                    'invalid',
                    $localKey,
                    $mapping['feeder_id'] ?? null,
                    $first->logMessage().' ('.$this->countLabel(count($errors)).' pada baris ini)',
                    [
                        'payload' => $payload,
                        'violations' => array_map(static fn ($violation) => $violation->toArray(), array_slice($errors, 0, 10)),
                    ],
                    $category,
                    $hint
                );

                return 'invalid';
            }
        }

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
            $context->log(
                'push',
                'failed',
                $localKey,
                $mapping['feeder_id'] ?? null,
                $exception->getMessage(),
                ['payload' => $payload],
                $this->errorCatalog?->categoryOf($exception->getMessage()),
                $this->errorCatalog?->hintFor($exception->getMessage())
            );

            return 'failed';
        }

        if (! $response->isOk()) {
            $message = $response->message();

            $context->log(
                'push',
                'failed',
                $localKey,
                $mapping['feeder_id'] ?? null,
                $message,
                ['payload' => $payload, 'response' => $response->payload()],
                $this->errorCatalog?->categoryOf($message),
                $this->errorCatalog?->hintFor($message)
            );

            return 'failed';
        }

        $feederId = $response->createdId() ?? ($mapping['feeder_id'] ?? null);

        $context->mappings->remember($syncer->key(), $localKey, $feederId === null ? null : (string) $feederId, $hash);

        $context->log('push', 'succeeded', $localKey, $feederId, "{$act} berhasil.", ['response' => $response->summary(200)]);

        return 'succeeded';
    }

    /**
     * Validasi pra-kirim aktif secara default; bisa dimatikan lewat setting atau
     * opsi `--skip-validation` bila data memang sudah diketahui bersih.
     */
    private function shouldValidate(SyncOptions $options): bool
    {
        if ($options->skipValidation || $this->validator === null) {
            return false;
        }

        return $this->settings?->bool('validate_before_push', true) ?? true;
    }

    /**
     * Jeda antar baris agar server feeder tidak dianggap membanjiri permintaan.
     */
    private function throttle(): void
    {
        $milliseconds = $this->settings?->int('request_delay_ms', 0) ?? 0;

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function countLabel(int $total): string
    {
        return number_format($total, 0, ',', '.').' temuan';
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
