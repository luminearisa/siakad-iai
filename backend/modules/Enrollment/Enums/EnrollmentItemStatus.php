<?php

namespace Modules\Enrollment\Enums;

enum EnrollmentItemStatus: string
{
    case ENROLLED = 'enrolled';
    case DROPPED = 'dropped';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
