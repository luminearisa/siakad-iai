<?php

declare(strict_types=1);

namespace Integrator\Reporting;

/**
 * Parameter satu kali pemeriksaan validasi + cakupan pelaporan.
 */
final class ValidationOptions
{
    public function __construct(
        public readonly ?string $entity = null,
        public readonly ?string $semesterCode = null,
        public readonly int $limit = 0,
        public readonly int $maxFindingsPerEntity = 500,
        public readonly bool $store = true,
        public readonly bool $includeNotes = true
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, ?string $defaultSemester = null): self
    {
        $entity = $input['entity'] ?? null;
        $semester = $input['semester'] ?? null;
        $limit = $input['limit'] ?? 0;

        return new self(
            entity: is_string($entity) && $entity !== '' ? $entity : null,
            semesterCode: is_string($semester) && $semester !== '' ? $semester : $defaultSemester,
            limit: is_numeric($limit) ? max(0, (int) $limit) : 0,
            maxFindingsPerEntity: isset($input['max_findings']) && is_numeric($input['max_findings'])
                ? max(1, (int) $input['max_findings'])
                : 500,
            store: ! (bool) ($input['no_store'] ?? false),
            includeNotes: ! (bool) ($input['no_notes'] ?? false)
        );
    }
}
