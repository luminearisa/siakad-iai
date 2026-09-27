<?php

declare(strict_types=1);

namespace Integrator\Sync;

use Integrator\Sync\Syncers\FeederReferenceSyncer;
use RuntimeException;

/**
 * All syncers, built from `config/feeder_mapping.php`.
 */
final class Registry
{
    /** @var array<string, SyncerInterface> */
    private array $syncers = [];

    public function __construct(private readonly FieldMapper $mapper)
    {
        $this->syncers['reference'] = new FeederReferenceSyncer($mapper->referenceConfig());

        foreach ($mapper->entities() as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $this->syncers[(string) $key] = new ConfigDrivenSyncer((string) $key, $config, $mapper);
        }
    }

    /**
     * @return array<string, SyncerInterface>
     */
    public function all(): array
    {
        return $this->syncers;
    }

    /**
     * Data syncers (everything except the feeder reference pull).
     *
     * @return array<string, SyncerInterface>
     */
    public function dataSyncers(): array
    {
        return array_filter($this->syncers, static fn (SyncerInterface $syncer) => $syncer->key() !== 'reference');
    }

    public function has(string $key): bool
    {
        return isset($this->syncers[$key]);
    }

    public function get(string $key): SyncerInterface
    {
        if (! $this->has($key)) {
            throw new RuntimeException("Entity [{$key}] tidak dikenal.");
        }

        return $this->syncers[$key];
    }

    /**
     * Resolve the run order for one entity, dependencies first.
     *
     * @return array<int, string>
     */
    public function order(string $key): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (string $current) use (&$visit, &$ordered, &$visiting): void {
            if (in_array($current, $ordered, true)) {
                return;
            }

            if (in_array($current, $visiting, true)) {
                throw new RuntimeException("Ketergantungan melingkar pada entity [{$current}].");
            }

            $visiting[] = $current;

            foreach ($this->get($current)->dependsOn() as $dependency) {
                if ($this->has($dependency)) {
                    $visit($dependency);
                }
            }

            $visiting = array_values(array_diff($visiting, [$current]));
            $ordered[] = $current;
        };

        $visit($key);

        return $ordered;
    }

    /**
     * Grouped list for the sync screen.
     *
     * @return array<string, array<int, SyncerInterface>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $syncer) {
            $group = (string) ($syncer->config()['group'] ?? 'Lain-lain');
            $groups[$group][] = $syncer;
        }

        return $groups;
    }
}
