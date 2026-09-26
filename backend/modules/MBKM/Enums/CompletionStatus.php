<?php

namespace Modules\MBKM\Enums;

enum CompletionStatus: string
{
    case PENDING = 'pending';
    case REQUIREMENTS_UNMET = 'requirements_unmet';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Verifikasi',
            self::REQUIREMENTS_UNMET => 'Syarat Belum Lengkap',
            self::COMPLETED => 'Selesai',
            self::REJECTED => 'Ditolak',
        };
    }
}
