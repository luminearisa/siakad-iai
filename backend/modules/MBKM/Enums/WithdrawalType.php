<?php

namespace Modules\MBKM\Enums;

enum WithdrawalType: string
{
    case WITHDRAWAL = 'withdrawal';
    case CANCELLATION = 'cancellation';
    case TERMINATION = 'termination';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::WITHDRAWAL => 'Pengunduran Diri',
            self::CANCELLATION => 'Pembatalan',
            self::TERMINATION => 'Penghentian',
        };
    }
}
