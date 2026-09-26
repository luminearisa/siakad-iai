<?php

namespace Modules\MBKM\Enums;

/**
 * Who is allowed to score a given assessment component.
 *
 * Kept as a single source of truth so the component configuration and the score
 * recording endpoints validate against exactly the same vocabulary — the list
 * must never be duplicated inline in a controller.
 */
enum AssessorType: string
{
    case INTERNAL_SUPERVISOR = 'internal_supervisor';
    case CO_SUPERVISOR = 'co_supervisor';
    case FIELD_SUPERVISOR = 'field_supervisor';
    case PARTNER = 'partner';
    case SELF = 'self';
    case COMMITTEE = 'committee';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL_SUPERVISOR => 'Pembimbing Internal',
            self::CO_SUPERVISOR => 'Co-Pembimbing',
            self::FIELD_SUPERVISOR => 'Pembimbing Lapangan',
            self::PARTNER => 'Mitra',
            self::SELF => 'Penilaian Diri',
            self::COMMITTEE => 'Tim Penguji',
        };
    }
}
