<?php

namespace Modules\MBKM\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case VERIFIED = 'verified';
    case REVISION_REQUIRED = 'revision_required';
    case SELECTED = 'selected';
    case NOT_SELECTED = 'not_selected';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses that count as an "active" application for duplicate detection.
     */
    public static function activeStatuses(): array
    {
        return [
            self::DRAFT->value,
            self::SUBMITTED->value,
            self::UNDER_REVIEW->value,
            self::VERIFIED->value,
            self::REVISION_REQUIRED->value,
            self::SELECTED->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::UNDER_REVIEW => 'Sedang Diverifikasi',
            self::VERIFIED => 'Terverifikasi',
            self::REVISION_REQUIRED => 'Perlu Perbaikan',
            self::SELECTED => 'Lolos Seleksi',
            self::NOT_SELECTED => 'Tidak Lolos',
            self::REJECTED => 'Ditolak',
            self::WITHDRAWN => 'Dibatalkan Mahasiswa',
        };
    }
}
