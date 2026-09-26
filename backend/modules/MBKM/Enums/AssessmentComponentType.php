<?php

namespace Modules\MBKM\Enums;

/**
 * The kind of evidence an assessment component represents.
 *
 * Distinct from AssessorType: this describes *what* is being graded, while
 * AssessorType describes *who* grades it. Programs may extend this vocabulary
 * through configuration, so it is only used to validate shape, not behaviour.
 */
enum AssessmentComponentType: string
{
    case PERFORMANCE = 'performance';
    case LOGBOOK = 'logbook';
    case FINAL_PROJECT = 'final_project';
    case PARTNER = 'partner';
    case PRESENTATION = 'presentation';
    case REPORT = 'report';
    case SELF = 'self';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PERFORMANCE => 'Kinerja',
            self::LOGBOOK => 'Logbook',
            self::FINAL_PROJECT => 'Proyek Akhir',
            self::PARTNER => 'Penilaian Mitra',
            self::PRESENTATION => 'Presentasi',
            self::REPORT => 'Laporan',
            self::SELF => 'Penilaian Diri',
            self::OTHER => 'Lainnya',
        };
    }
}
