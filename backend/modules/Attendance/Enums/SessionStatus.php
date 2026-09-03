<?php

namespace Modules\Attendance\Enums;

enum SessionStatus: string
{
    case SCHEDULED = 'scheduled'; // Dijadwalkan
    case OPEN = 'open';           // Sesi Berlangsung / Presensi Dibuka
    case CLOSED = 'closed';       // Selesai / Terkunci
    case CANCELLED = 'cancelled'; // Dibatalkan

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Dijadwalkan',
            self::OPEN => 'Sedang Berlangsung',
            self::CLOSED => 'Selesai / Terkunci',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
