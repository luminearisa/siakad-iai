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
}
