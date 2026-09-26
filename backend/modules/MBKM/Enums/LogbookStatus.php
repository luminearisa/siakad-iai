<?php

namespace Modules\MBKM\Enums;

enum LogbookStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVISION_REQUIRED = 'revision_required';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * A finalized logbook is frozen: the student may no longer edit it through the
     * regular endpoints.
     */
    public function isFinalized(): bool
    {
        return in_array($this->value, [self::APPROVED->value, self::REJECTED->value], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Menunggu Verifikasi',
            self::REVISION_REQUIRED => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }
}
