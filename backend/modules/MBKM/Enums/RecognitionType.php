<?php

namespace Modules\MBKM\Enums;

/**
 * How an MBKM activity is converted into academic credit.
 *
 * - COURSE_CONVERSION: activity maps to one or more specific courses (Model 1).
 * - CREDIT_WEIGHT: activity earns a number of SKS based on the program policy
 *   before being mapped to academic recognition (Model 2).
 */
enum RecognitionType: string
{
    case COURSE_CONVERSION = 'course_conversion';
    case CREDIT_WEIGHT = 'credit_weight';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::COURSE_CONVERSION => 'Konversi Mata Kuliah',
            self::CREDIT_WEIGHT => 'Bobot SKS Program',
        };
    }
}
