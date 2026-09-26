<?php

namespace Modules\MBKM\Enums;

enum IssueStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::IN_PROGRESS => 'Ditangani',
            self::RESOLVED => 'Terselesaikan',
            self::CLOSED => 'Ditutup',
        };
    }
}
