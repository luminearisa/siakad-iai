<?php

namespace Modules\MBKM\Enums;

enum SupervisorRole: string
{
    case INTERNAL = 'internal';
    case CO_SUPERVISOR = 'co_supervisor';
    case FIELD = 'field';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'Pembimbing Internal',
            self::CO_SUPERVISOR => 'Co-Pembimbing',
            self::FIELD => 'Pembimbing Lapangan',
        };
    }
}
