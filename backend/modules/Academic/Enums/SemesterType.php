<?php

namespace Modules\Academic\Enums;

enum SemesterType: string
{
    case GANJIL = 'ganjil';
    case GENAP = 'genap';
    case PENDEK = 'pendek';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
