<?php

namespace Modules\Advising\Enums;

enum AdvisorStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case TRANSFERRED = 'transferred';
    case COMPLETED = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
