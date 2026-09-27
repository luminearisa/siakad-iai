<?php

declare(strict_types=1);

namespace Integrator\Reporting;

use Integrator\Siakad\SiakadClient;
use Integrator\Siakad\SiakadException;
use Integrator\Sync\MappingRepository;
use Integrator\Sync\ReferenceResolver;
use Integrator\Sync\Registry;
use Integrator\Sync\SyncContext;
use Integrator\Sync\SyncOptions;
use Integrator\Sync\SyncerInterface;
use Integrator\Support\Database;
use Integrator\Support\Settings;
use Integrator\Validation\FeederValidator;
use Integrator\Validation\ValidationRepository;
use Integrator\Validation\Violation;
use RuntimeException;
use Throwable;

/**
 * Pemeriksaan kesiapan pelaporan: menelusuri data SIAKAD per entity, memetakan
 * seperti saat sinkronisasi, menjalankan validasi aturan Neo Feeder, lalu
 * menghitung cakupan pelaporan (berapa persen data sudah terkirim per prodi).
 *
 * Ini menjawab dua kebutuhan operator yang tidak bisa dijawab sinkronisasi
 * biasa: "data mana yang akan ditolak feeder" dan "seberapa lengkap laporan
 * semester ini per program studi".
 */
final class ValidationPass
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $semesterCache = null;

    public function __construct(
        private readonly SiakadClient $siakad,
        private readonly Registry $registry,
        private readonly FeederValidator $validator,
        private readonly ValidationRepository $repository,
        private readonly MappingRepository $mappings,
        private readonly ReferenceResolver $references,
        private readonly Settings $settings,
        private readonly Database $db
    ) {}

    public function run(ValidationOptions $options): ValidationSummary
    {
        $started = microtime(true);

        if (! $options->semesterCode) {
            $default = $this->settings->get('default_semester_code');
            $options = new ValidationOptions(
                entity: $options->entity,
                semesterCode: is_string($default) && $default !== '' ? $default : null,
                limit: $options->limit,
                maxFindingsPerEntity: $options->maxFindingsPerEntity,
                store: $options->store,
                includeNotes: $options->includeNotes
            );
        }

        $syncers = $this->syncers($options->entity);
        $runId = $options->store
            ? $this->repository->startRun($options->entity ?? 'semua', $options->semesterCode, array_keys($syncers))
            : 'lokal-'.gmdate('YmdHis');

        $entities = [];
        $coverage = [];
        $findings = [];
        $errors = [];
        $totals = [
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
            'warnings' => 0,
            'findings' => 0,
            'synced' => 0,
            'pending' => 0,
            'stale' => 0,
            'percentage' => 0.0,
        ];

        foreach ($syncers as $key => $syncer) {
            if ($syncer->sourceEndpoint() === null) {
                // Entitas `reference` menarik data dari feeder, bukan dari SIAKAD.
                continue;
            }

            try {
                $scan = $this->scan($syncer, $runId, $options);
            } catch (SiakadException $exception) {
                $errors[] = $key.': '.$exception->getMessage().' '.$exception->hint();

                continue;
            } catch (Throwable $exception) {
                $errors[] = $key.': '.$exception->getMessage();

                continue;
            }

            $entities[$key] = [
                'label' => $this->validator->labelFor($key),
                'period' => $scan['period'],
            ] + $scan['counts'];

            $entities[$key]['percentage'] = $scan['counts']['total'] > 0
                ? round($scan['counts']['synced'] / $scan['counts']['total'] * 100, 2)
                : 0.0;

            foreach ($scan['buckets'] as $bucket) {
                $coverage[] = $bucket;
            }

            foreach ($scan['findings'] as $violation) {
                $findings[] = $violation;
            }

            foreach ($scan['counts'] as $counter => $value) {
                if (isset($totals[$counter])) {
                    $totals[$counter] += (int) $value;
                }
            }
        }

        $totals['percentage'] = $totals['total'] > 0
            ? round($totals['synced'] / $totals['total'] * 100, 2)
            : 0.0;

        if ($options->store) {
            $this->repository->saveFindings($runId, $findings);
            $this->repository->saveCoverage($runId, $coverage);
            $this->repository->finishRun(
                $runId,
                $totals,
                $errors === [] ? 'finished' : 'partial',
                $errors === [] ? null : implode(' | ', array_slice($errors, 0, 5))
            );
        }

        return new ValidationSummary(
            runId: $runId,
            scope: $options->entity ?? 'semua',
            semester: $options->semesterCode,
            entities: $entities,
            totals: $totals,
            errors: $errors,
            durationSeconds: microtime(true) - $started,
            stored: $options->store
        );
    }

    /**
     * @return array<string, SyncerInterface>
     */
    private function syncers(?string $entity): array
    {
        $all = $this->registry->all();

        if ($entity === null || $entity === '') {
            return $all;
        }

        if (! isset($all[$entity])) {
            throw new RuntimeException("Entity [{$entity}] tidak dikenal. Jalankan `sync:list` untuk melihat daftar entity.");
        }

        return [$entity => $all[$entity]];
    }

    /**
     * Telusuri satu entity: validasi tiap baris + hitung cakupan per prodi.
     *
     * @return array{counts: array<string, int>, buckets: array<int, array<string, mixed>>, findings: array<int, Violation>, period: ?string}
     */
    private function scan(SyncerInterface $syncer, string $runId, ValidationOptions $options): array
    {
        $endpoint = $syncer->sourceEndpoint();

        if ($endpoint === null) {
            throw new RuntimeException("Entity [{$syncer->key()}] tidak memiliki sumber SIAKAD.");
        }

        $syncOptions = $this->syncOptionsFor($syncer, $options);
        $period = $syncOptions->semesterCode;
        $context = new SyncContext($runId, $syncer->key(), $syncOptions, $this->references, $this->mappings, $this->db);

        $counts = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'warnings' => 0, 'synced' => 0, 'pending' => 0, 'stale' => 0];
        $buckets = [];
        $findings = [];
        $stored = 0;
        $truncated = false;
        $maxFindings = max(1, $options->maxFindingsPerEntity);

        $this->siakad->each(
            $endpoint,
            $syncer->sourceQuery($syncOptions),
            function (array $row) use (
                $syncer,
                $context,
                $period,
                $options,
                $maxFindings,
                &$counts,
                &$buckets,
                &$findings,
                &$stored,
                &$truncated
            ): bool {
                foreach ($syncer->expand($row, $context) as $expanded) {
                    $inspection = $this->inspect($syncer, $expanded, $context, $period);

                    $counts['total']++;
                    $counts['valid'] += $inspection['has_error'] ? 0 : 1;
                    $counts['invalid'] += $inspection['has_error'] ? 1 : 0;
                    $counts['warnings'] += (! $inspection['has_error'] && $inspection['violations'] !== []) ? 1 : 0;
                    $counts['synced'] += $inspection['synced'] ? 1 : 0;
                    $counts['pending'] += (! $inspection['synced'] && ! $inspection['has_error']) ? 1 : 0;
                    $counts['stale'] += $inspection['stale'] ? 1 : 0;

                    $bucketKey = $inspection['prodi'] ?? '-';
                    $buckets[$bucketKey] ??= [
                        'entity' => $syncer->key(),
                        'period' => $period ?? '-',
                        'prodi' => $bucketKey,
                        'prodi_label' => $inspection['prodi_label'],
                        'total' => 0,
                        'valid' => 0,
                        'invalid' => 0,
                        'warnings' => 0,
                        'synced' => 0,
                        'pending' => 0,
                        'stale' => 0,
                        'percentage' => 0.0,
                    ];

                    $buckets[$bucketKey]['total']++;
                    $buckets[$bucketKey]['valid'] += $inspection['has_error'] ? 0 : 1;
                    $buckets[$bucketKey]['invalid'] += $inspection['has_error'] ? 1 : 0;
                    $buckets[$bucketKey]['warnings'] += (! $inspection['has_error'] && $inspection['violations'] !== []) ? 1 : 0;
                    $buckets[$bucketKey]['synced'] += $inspection['synced'] ? 1 : 0;
                    $buckets[$bucketKey]['pending'] += (! $inspection['synced'] && ! $inspection['has_error']) ? 1 : 0;
                    $buckets[$bucketKey]['stale'] += $inspection['stale'] ? 1 : 0;

                    foreach ($inspection['violations'] as $violation) {
                        if ($stored >= $maxFindings) {
                            if (! $truncated) {
                                $truncated = true;
                                $findings[] = new Violation(
                                    entity: $syncer->key(),
                                    code: 'temuan_dipotong',
                                    severity: Violation::NOTE,
                                    message: 'Temuan dibatasi '.number_format($maxFindings, 0, ',', '.').' baris pertama per entity; perbaiki temuan yang tampil lalu periksa ulang.',
                                    period: $period,
                                    entityLevel: true
                                );
                                $stored++;
                            }

                            continue;
                        }

                        $findings[] = $violation;
                        $stored++;
                    }
                }

                return true;
            },
            $options->limit
        );

        if ($options->includeNotes) {
            $findings = array_merge($findings, $this->validator->notes($syncer->key(), $period));
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['percentage'] = $bucket['total'] > 0
                ? round($bucket['synced'] / $bucket['total'] * 100, 2)
                : 0.0;
        }

        return [
            'counts' => $counts,
            'buckets' => array_values($buckets),
            'findings' => $findings,
            'period' => $period,
        ];
    }

    /**
     * Periksa satu baris hasil pemetaan: aturan Neo Feeder + status sinkronisasi.
     *
     * @param  array<string, mixed>  $row
     * @return array{violations: array<int, Violation>, has_error: bool, synced: bool, stale: bool, prodi: ?string, prodi_label: ?string}
     */
    private function inspect(SyncerInterface $syncer, array $row, SyncContext $context, ?string $period): array
    {
        try {
            $localKey = $syncer->localKey($row);
        } catch (Throwable) {
            $localKey = '';
        }

        if ($localKey === '') {
            return [
                'violations' => [new Violation(
                    entity: $syncer->key(),
                    code: 'kunci_lokal_kosong',
                    severity: Violation::ERROR,
                    message: 'Kunci lokal tidak dapat dibentuk dari baris sumber, sehingga baris ini tidak bisa dilacak.',
                    period: $period
                )],
                'has_error' => true,
                'synced' => false,
                'stale' => false,
                'prodi' => null,
                'prodi_label' => null,
            ];
        }

        $mapping = $this->mappings->find($syncer->key(), $localKey);
        $payload = $syncer->payload($row, $context, $mapping !== null);
        $violations = $this->validator->validateRow($syncer->key(), $localKey, $payload, $row, $period);

        $hasError = false;

        foreach ($violations as $violation) {
            if ($violation->isError()) {
                $hasError = true;

                break;
            }
        }

        $synced = $mapping !== null && ($mapping['feeder_id'] ?? null) !== null && (string) $mapping['feeder_id'] !== '';
        $stale = false;

        if ($synced && $syncer->actUpdate() !== null) {
            $hash = $syncer->payloadHash($row, $context, $payload);
            $stale = ($mapping['payload_hash'] ?? null) !== $hash;
        }

        $prodi = $this->validator->prodiFor($syncer->key(), $payload, $row);

        return [
            'violations' => $violations,
            'has_error' => $hasError,
            'synced' => $synced,
            'stale' => $stale,
            'prodi' => $prodi['key'] ?? null,
            'prodi_label' => $prodi['label'] ?? null,
        ];
    }

    private function syncOptionsFor(SyncerInterface $syncer, ValidationOptions $options): SyncOptions
    {
        if (! $syncer->requiresSemester()) {
            return new SyncOptions(dryRun: true, limit: $options->limit);
        }

        if ($options->semesterCode === null || $options->semesterCode === '') {
            throw new RuntimeException('Entity ini memerlukan semester. Isi --semester atau setting default_semester_code.');
        }

        $semesterId = $this->semesterIdFor($options->semesterCode);

        if ($semesterId === null) {
            throw new RuntimeException("Semester dengan kode feeder {$options->semesterCode} tidak ditemukan di SIAKAD.");
        }

        return new SyncOptions(
            dryRun: true,
            semesterCode: $options->semesterCode,
            semesterId: $semesterId,
            limit: $options->limit
        );
    }

    private function semesterIdFor(string $code): ?int
    {
        if ($this->semesterCache === null) {
            $this->semesterCache = $this->siakad->semesters();
        }

        foreach ($this->semesterCache as $semester) {
            if ((string) ($semester['feeder_code'] ?? '') === $code) {
                return (int) $semester['id'];
            }
        }

        return null;
    }
}
