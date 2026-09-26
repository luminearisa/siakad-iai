<?php

namespace Modules\Attendance\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present'; // Hadir (H)
    case PERMIT = 'permit';   // Izin (I)
    case SICK = 'sick';       // Sakit (S)
    case ABSENT = 'absent';   // Alpa (A)

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Hadir',
            self::PERMIT => 'Izin',
            self::SICK => 'Sakit',
            self::ABSENT => 'Alpa',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::PRESENT => 'H',
            self::PERMIT => 'I',
            self::SICK => 'S',
            self::ABSENT => 'A',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Izin/Sakit/Alpa tidak boleh tercatat tanpa dasar — keterangan wajib diisi.
     */
    public function requiresNote(): bool
    {
        return $this !== self::PRESENT;
    }

    /**
     * Status yang dihitung hadir dalam persentase kelayakan ujian.
     */
    public function countsAsAttended(): bool
    {
        return $this !== self::ABSENT;
    }
}
