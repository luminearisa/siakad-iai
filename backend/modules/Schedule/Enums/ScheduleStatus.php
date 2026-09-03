<?php

namespace Modules\Schedule\Enums;

enum ScheduleStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
