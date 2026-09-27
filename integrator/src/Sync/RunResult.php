<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * Outcome of one sync run.
 */
final class RunResult
{
    public function __construct(
        public readonly string $runId,
        public readonly string $entity,
        public readonly string $mode,
        public int $total = 0,
        public int $succeeded = 0,
        public int $failed = 0,
        public int $skipped = 0,
        public int $planned = 0,
        public int $invalid = 0,
        public ?string $error = null,
        public float $durationSeconds = 0.0,
        public int $pages = 0
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null && $this->failed === 0 && $this->invalid === 0;
    }

    /**
     * One-line summary for the CLI and the flash message.
     */
    public function summary(): string
    {
        $parts = [
            "total {$this->total}",
            "berhasil {$this->succeeded}",
            "dilewati {$this->skipped}",
            "gagal {$this->failed}",
            "tidak valid {$this->invalid}",
        ];

        if ($this->mode === 'dry-run') {
            $parts[] = "rencana {$this->planned}";
        }

        return sprintf(
            '%s [%s] %s dalam %.1fs',
            $this->entity,
            $this->mode,
            implode(', ', $parts),
            $this->durationSeconds
        );
    }
}
