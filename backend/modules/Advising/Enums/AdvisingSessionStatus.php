<?php

namespace Modules\Advising\Enums;

enum AdvisingSessionStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
