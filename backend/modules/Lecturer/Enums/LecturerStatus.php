<?php

namespace Modules\Lecturer\Enums;

enum LecturerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case RETIRED = 'retired';
    case RESIGNED = 'resigned';
    case DECEASED = 'deceased';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
