<?php

declare(strict_types=1);

namespace Integrator\Sync;

/**
 * Contract for a sync entity.
 *
 * Implementations are configuration driven ({@see ConfigDrivenSyncer}) so the
 * SIAKAD→Neo Feeder field mapping lives in `config/feeder_mapping.php` and can be
 * corrected by an operator when the installed Neo Feeder version expects different
 * column names — no PHP knowledge required.
 */
interface SyncerInterface
{
    /**
     * Machine name, also the `sync_logs.entity` value (`students`, `classes`, ...).
     */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * SIAKAD API scope required to read the source endpoint.
     */
    public function scope(): ?string;

    /**
     * Other entity keys that must run first.
     *
     * @return array<int, string>
     */
    public function dependsOn(): array;

    /**
     * Whether a semester (and therefore `semester_code`) is required.
     */
    public function requiresSemester(): bool;

    /**
     * SIAKAD endpoint, or null for syncers that only talk to the feeder.
     */
    public function sourceEndpoint(): ?string;

    /**
     * Query string sent to SIAKAD.
     *
     * @return array<string, mixed>
     */
    public function sourceQuery(SyncOptions $options): array;

    /**
     * Stable identity of a SIAKAD row, e.g. `nim:202501001`.
     *
     * @param  array<string, mixed>  $row
     */
    public function localKey(array $row): string;

    /**
     * Neo Feeder function used when the row does not exist yet.
     */
    public function actInsert(): ?string;

    /**
     * Neo Feeder function used when the row is already mapped, or null when the
     * endpoint is insert-only.
     */
    public function actUpdate(): ?string;

    /**
     * Expand one source row into the rows that each produce one feeder payload
     * (for example: one enrollment row → one payload per KRS item).
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $row, SyncContext $context): array;

    /**
     * Payload sent to Neo Feeder for one (expanded) row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function payload(array $row, SyncContext $context, bool $isUpdate): array;

    /**
     * Payload keys that must not be empty; a row missing one is skipped with a
     * clear message instead of being pushed as invalid national data.
     *
     * @return array<int, string>
     */
    public function requiredFields(): array;

    /**
     * Fingerprint used to detect "nothing changed since the last sync".
     *
     * Only values that originate from the SIAKAD record itself are included: ids
     * resolved from the feeder (id_prodi, id_semester, mapped feeder ids) and
     * literals are excluded. Without this, a row that was just inserted would be
     * pushed again on the next run simply because the update payload now contains
     * the feeder id returned by the insert.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $row, SyncContext $context, array $payload): string;

    /**
     * Full entity configuration (used by the UI to show what is being pushed).
     *
     * @return array<string, mixed>
     */
    public function config(): array;
}
