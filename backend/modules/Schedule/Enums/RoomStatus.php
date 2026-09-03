<?php

namespace Modules\Schedule\Enums;

enum RoomStatus: string
{
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case INACTIVE = 'inactive';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
