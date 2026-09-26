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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Sesi `closed` boleh dibuka kembali, tetapi hanya lewat jalur yang mewajibkan
     * alasan (AttendanceService::reopenSession) supaya perubahannya ter-audit.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::SCHEDULED => [self::OPEN, self::CLOSED, self::CANCELLED],
            self::OPEN => [self::CLOSED, self::CANCELLED],
            self::CLOSED => [self::OPEN],
            self::CANCELLED => [self::SCHEDULED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    /**
     * Sesi terkunci = presensinya sudah final dan hanya boleh diubah lewat koreksi beralasan.
     */
    public function isLocked(): bool
    {
        return $this === self::CLOSED;
    }
}
