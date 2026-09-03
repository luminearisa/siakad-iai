<?php

namespace Modules\Academic\Enums;

enum DegreeLevel: string
{
    case D3 = 'D3';
    case D4 = 'D4';
    case S1 = 'S1';
    case S2 = 'S2';
    case S3 = 'S3';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
