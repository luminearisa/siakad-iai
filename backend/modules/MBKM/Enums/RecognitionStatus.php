<?php

namespace Modules\MBKM\Enums;

enum RecognitionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case LOCKED = 'locked';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Approved/locked recognitions must not be edited through the normal endpoints.
     */
    public function isLocked(): bool
    {
        return in_array($this->value, [self::APPROVED->value, self::LOCKED->value], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::REVIEWED => 'Ditinjau',
            self::APPROVED => 'Disetujui',
            self::LOCKED => 'Terkunci',
            self::REJECTED => 'Ditolak',
        };
    }
}
