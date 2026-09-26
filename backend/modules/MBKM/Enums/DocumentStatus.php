<?php

namespace Modules\MBKM\Enums;

enum DocumentStatus: string
{
    case UPLOADED = 'uploaded';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'Diunggah',
            self::VERIFIED => 'Terverifikasi',
            self::REJECTED => 'Ditolak',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }
}
