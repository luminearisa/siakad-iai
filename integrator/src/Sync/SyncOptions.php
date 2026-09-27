<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * Options for one sync run.
 */
final class SyncOptions
{
    public function __construct(
        public readonly bool $dryRun = true,
        public readonly ?string $semesterCode = null,
        public readonly ?int $semesterId = null,
        public readonly int $limit = 0,
        public readonly bool $force = false,
        public readonly ?string $onlyLocalKey = null,
        public readonly bool $skipValidation = false
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $semesterCode = $input['semester_code'] ?? null;
        $semesterCode = is_string($semesterCode) && trim($semesterCode) !== '' ? trim($semesterCode) : null;

        return new self(
            dryRun: ! empty($input['dry_run']),
            semesterCode: $semesterCode,
            semesterId: isset($input['semester_id']) && is_numeric($input['semester_id']) ? (int) $input['semester_id'] : null,
            limit: isset($input['limit']) && is_numeric($input['limit']) ? max(0, (int) $input['limit']) : 0,
            force: ! empty($input['force']),
            onlyLocalKey: isset($input['local_key']) && is_string($input['local_key']) && $input['local_key'] !== ''
                ? $input['local_key']
                : null,
            skipValidation: ! empty($input['skip_validation'])
        );
    }

    public function mode(): string
    {
        return $this->dryRun ? 'dry-run' : 'live';
    }
}
