<?php

namespace Modules\Class\Enums;

enum ClassLecturerRole: string
{
    case PRIMARY = 'primary';
    case ASSISTANT = 'assistant';
    case CO_LECTURER = 'co_lecturer';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
