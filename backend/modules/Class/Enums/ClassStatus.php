<?php

namespace Modules\Class\Enums;

enum ClassStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
