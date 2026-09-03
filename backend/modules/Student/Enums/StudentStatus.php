<?php

namespace Modules\Student\Enums;

enum StudentStatus: string
{
    case PROSPECTIVE = 'prospective';
    case ACTIVE = 'active';
    case LEAVE = 'leave';
    case INACTIVE = 'inactive';
    case GRADUATED = 'graduated';
    case WITHDRAWN = 'withdrawn';
    case DISMISSED = 'dismissed';
    case DECEASED = 'deceased';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
