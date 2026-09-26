<?php

namespace Modules\MBKM\Enums;

/**
 * Attendance status for MBKM activities.
 *
 * Kept as a distinct enum from the regular lecture attendance status so the MBKM
 * boundary is explicit; the value set mirrors the academic attendance vocabulary.
 */
enum MbkmAttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case EXCUSED = 'excused';
    case SICK = 'sick';
    case ABSENT = 'absent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Hadir',
            self::LATE => 'Terlambat',
            self::EXCUSED => 'Izin',
            self::SICK => 'Sakit',
            self::ABSENT => 'Alfa',
        };
    }

    /**
     * Whether the status counts towards the attendance percentage.
     */
    public function countsAsPresent(): bool
    {
        return in_array($this->value, [self::PRESENT->value, self::LATE->value], true);
    }
}
