<?php

namespace Modules\Course\Enums;

enum CourseType: string
{
    case THEORY = 'theory';
    case PRACTICAL = 'practical';
    case MIXED = 'mixed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
