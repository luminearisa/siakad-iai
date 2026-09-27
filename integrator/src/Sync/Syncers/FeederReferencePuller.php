<?php

declare(strict_types=1);

namespace Integrator\Sync\Syncers;

use Integrator\NeoFeeder\NeoFeederClient;
use Integrator\Sync\ReferenceResolver;
use Integrator\Sync\SyncContext;

/**
 * A syncer that only reads from the feeder.
 */
interface FeederReferencePuller
{
    /**
     * Pull the reference tables this installation needs (PT, prodi, periode,
     * dosen, kategori kegiatan, kamus kolom).
     *
     * @return array<string, array{label: string, status: string, count: int, message: string}>
     */
    public function pull(NeoFeederClient $feeder, ReferenceResolver $references, SyncContext $context): array;
}
