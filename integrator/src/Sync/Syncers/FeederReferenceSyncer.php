<?php

declare(strict_types=1);

namespace Integrator\Sync\Syncers;

use Integrator\NeoFeeder\NeoFeederClient;
use Integrator\Sync\ReferenceResolver;
use Integrator\Sync\SyncContext;
use Integrator\Sync\SyncOptions;
use Integrator\Sync\SyncerInterface;
use Integrator\Sync\Template;

/**
 * Pulls the reference data that every other syncer depends on.
 *
 * This is the one entity that flows the other way (feeder → local): PDDikti
 * identifiers (id_prodi, id_semester, id_dosen) are installation specific, so they
 * are cached locally instead of being configured by hand.
 */
final class FeederReferenceSyncer implements SyncerInterface, FeederReferencePuller
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function key(): string
    {
        return 'reference';
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? 'Referensi Neo Feeder');
    }

    public function description(): string
    {
        return (string) ($this->config['description'] ?? 'Menarik profil PT, prodi, periode, dosen, kategori kegiatan, dan kamus kolom dari Neo Feeder.');
    }

    public function scope(): ?string
    {
        return null;
    }

    /**
     * @return array<int, string>
     */
    public function dependsOn(): array
    {
        return [];
    }

    public function requiresSemester(): bool
    {
        return false;
    }

    public function sourceEndpoint(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceQuery(SyncOptions $options): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function localKey(array $row): string
    {
        return '';
    }

    public function actInsert(): ?string
    {
        return null;
    }

    public function actUpdate(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $row, SyncContext $context): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function payload(array $row, SyncContext $context, bool $isUpdate): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function requiredFields(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $row, SyncContext $context, array $payload): string
    {
        return sha1((string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function pull(NeoFeederClient $feeder, ReferenceResolver $references, SyncContext $context): array
    {
        $sources = $this->config['sources'] ?? [];
        $report = [];

        if (! is_array($sources)) {
            return $report;
        }

        foreach ($sources as $kind => $source) {
            if (! is_array($source)) {
                continue;
            }

            $label = (string) ($source['label'] ?? $kind);
            $act = (string) ($source['act'] ?? '');
            $limit = (int) ($source['limit'] ?? 1000);

            $response = $feeder->call($act, ['limit' => $limit, 'offset' => 0]);

            if (! $response->isOk()) {
                $report[$kind] = [
                    'label' => $label,
                    'status' => 'failed',
                    'count' => 0,
                    'message' => $response->message(),
                ];

                $context->log($act, 'failed', null, null, $response->message(), ['kind' => $kind]);

                continue;
            }

            $count = 0;

            foreach ($response->rows() as $row) {
                $key = $this->resolveKey($row, $source);

                if ($key === '') {
                    continue;
                }

                $idField = $source['id_field'] ?? null;
                $id = is_string($idField) && $idField !== ''
                    ? (string) ($row[$idField] ?? $key)
                    : $key;

                $references->store((string) $kind, $key, $id, $row);
                $count++;
            }

            $report[$kind] = [
                'label' => $label,
                'status' => 'success',
                'count' => $count,
                'message' => $count === 0 ? 'Tidak ada baris dikembalikan feeder.' : '',
            ];

            $context->log($act, 'success', null, null, "Referensi {$label}: {$count} baris.", ['kind' => $kind]);
        }

        return $report;
    }

    /**
     * First non-empty key template wins, so `{nidn}` can fall back to `{nip}`.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $source
     */
    private function resolveKey(array $row, array $source): string
    {
        $templates = $source['key_templates'] ?? $source['key_template'] ?? [];

        if (is_string($templates)) {
            $templates = [$templates];
        }

        foreach ((array) $templates as $template) {
            if (! is_string($template) || $template === '') {
                continue;
            }

            $value = Template::render($template, $row);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
