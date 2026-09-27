<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * A syncer whose payload rules come from `config/feeder_mapping.php`.
 */
final class ConfigDrivenSyncer implements SyncerInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $key,
        private readonly array $config,
        private readonly FieldMapper $mapper
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? $this->key);
    }

    public function description(): string
    {
        return (string) ($this->config['description'] ?? '');
    }

    public function scope(): ?string
    {
        $scope = $this->config['source']['scope'] ?? null;

        return is_string($scope) && $scope !== '' ? $scope : null;
    }

    /**
     * @return array<int, string>
     */
    public function dependsOn(): array
    {
        $depends = $this->config['depends_on'] ?? [];

        return is_array($depends) ? array_values(array_filter($depends, 'is_string')) : [];
    }

    public function requiresSemester(): bool
    {
        return (bool) ($this->config['source']['semester'] ?? false);
    }

    public function sourceEndpoint(): ?string
    {
        $endpoint = $this->config['source']['endpoint'] ?? null;

        return is_string($endpoint) && $endpoint !== '' ? $endpoint : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceQuery(SyncOptions $options): array
    {
        $query = $this->config['source']['query'] ?? [];
        $query = is_array($query) ? $query : [];

        if ($this->requiresSemester() && $options->semesterId !== null) {
            $query['semester_id'] = $options->semesterId;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function localKey(array $row): string
    {
        return Template::render((string) ($this->config['local_key'] ?? ''), $row);
    }

    public function actInsert(): ?string
    {
        $act = $this->config['act_insert'] ?? null;

        return is_string($act) && $act !== '' ? $act : null;
    }

    public function actUpdate(): ?string
    {
        $act = $this->config['act_update'] ?? null;

        return is_string($act) && $act !== '' ? $act : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $row, SyncContext $context): array
    {
        $iterate = $this->config['iterate'] ?? null;

        if ($iterate === null) {
            return [$row];
        }

        $path = is_array($iterate) ? ($iterate['path'] ?? null) : $iterate;
        $alias = is_array($iterate) ? ($iterate['alias'] ?? 'item') : 'item';

        if (! is_string($path) || $path === '') {
            return [$row];
        }

        $children = ArrayPath::get($row, $path);

        if (! is_array($children)) {
            return [];
        }

        $expanded = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $expanded[] = $row + [(string) $alias => $child];
        }

        return $this->applyConditions($expanded);
    }

    /**
     * Keep only the expanded rows that match every `only_if` condition.
     *
     * Example: `'only_if' => ['item.status' => 'enrolled']` makes the KRS syncer
     * ignore dropped items instead of sending a row PDDikti would reject.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyConditions(array $rows): array
    {
        $conditions = $this->config['only_if'] ?? [];

        if (! is_array($conditions) || $conditions === []) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($conditions): bool {
            foreach ($conditions as $path => $expected) {
                $actual = ArrayPath::get($row, (string) $path);

                if (is_array($expected)) {
                    if (! in_array($actual, $expected, true)) {
                        return false;
                    }

                    continue;
                }

                if ((string) $actual !== (string) $expected) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function payload(array $row, SyncContext $context, bool $isUpdate): array
    {
        return $this->mapper->build($this->config, $row, $context, $isUpdate);
    }

    /**
     * @return array<int, string>
     */
    public function requiredFields(): array
    {
        $required = $this->config['required'] ?? [];

        return is_array($required) ? array_values(array_filter($required, 'is_string')) : [];
    }

    /**
     * Fingerprint of the SIAKAD-owned part of the payload.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $row, SyncContext $context, array $payload): string
    {
        $fields = $this->config['fields'] ?? [];
        $local = [];

        foreach ($payload as $key => $value) {
            $spec = (string) ($fields[$key] ?? '');

            // Skip values that come from the feeder/reference world or are constants:
            // they do not tell us whether the SIAKAD record changed.
            if (str_starts_with($spec, '@mapping:') || str_starts_with($spec, '@ref:') || str_starts_with($spec, '@semester') || str_starts_with($spec, '@literal:')) {
                continue;
            }

            $local[$key] = $value;
        }

        ksort($local);

        return sha1((string) json_encode($local, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }
}
