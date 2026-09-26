<?php

namespace Modules\Attendance\Support;

use Carbon\CarbonInterface;
use Modules\Academic\Models\Semester;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\TeachingSession;

/**
 * Kebijakan kehadiran: dari mana ambang "layak ujian" dan denominator persentase.
 *
 * Angka tidak boleh hardcoded di service maupun UI — semester punya kolom
 * `min_attendance_uts_percentage` / `min_attendance_uas_percentage` dan
 * `total_teaching_weeks` yang menjadi sumber kebenaran.
 */
class AttendancePolicy
{
    public const FALLBACK_MINIMUM_PERCENTAGE = 75.0;
    public const FALLBACK_TEACHING_WEEKS = 16;

    /**
     * Sebelum tanggal UTS memakai ambang UTS, sesudahnya memakai ambang UAS.
     *
     * @return array{stage: string, percentage: float}
     */
    public static function threshold(?Semester $semester, ?CarbonInterface $asOf = null): array
    {
        $today = ($asOf ?? now())->startOfDay();
        $utsStart = $semester?->uts_start_date;

        if ($utsStart && $today->lt($utsStart->copy()->startOfDay())) {
            return [
                'stage' => 'uts',
                'percentage' => self::floatOf($semester?->min_attendance_uts_percentage),
            ];
        }

        return [
            'stage' => 'uas',
            'percentage' => self::floatOf($semester?->min_attendance_uas_percentage),
        ];
    }

    public static function totalTeachingWeeks(?Semester $semester): int
    {
        return (int) ($semester?->total_teaching_weeks ?: self::FALLBACK_TEACHING_WEEKS);
    }

    /**
     * denominator persentase = pertemuan yang sudah benar-benar berlangsung.
     * Pertemuan masa depan belum boleh menurunkan persentase mahasiswa, dan sesi
     * dibatalkan tidak dihitung.
     *
     * @param  \Illuminate\Support\Collection<int, TeachingSession>  $sessions
     */
    public static function heldSessions(\Illuminate\Support\Collection $sessions, ?CarbonInterface $asOf = null): \Illuminate\Support\Collection
    {
        $today = ($asOf ?? now())->startOfDay();

        return $sessions->filter(function (TeachingSession $session) use ($today) {
            if ($session->status === SessionStatus::CANCELLED) {
                return false;
            }

            if ($session->status === SessionStatus::CLOSED) {
                return true;
            }

            return $session->session_date !== null && !$session->session_date->copy()->startOfDay()->gt($today);
        })->values();
    }

    /**
     * @return array{percentage: float, is_eligible: bool}
     */
    public static function eligibility(int $attended, int $held, ?Semester $semester, ?CarbonInterface $asOf = null): array
    {
        $threshold = self::threshold($semester, $asOf);

        if ($held <= 0) {
            return ['percentage' => 0.0, 'is_eligible' => false];
        }

        $percentage = round(($attended / $held) * 100, 1);

        return ['percentage' => $percentage, 'is_eligible' => $percentage >= $threshold['percentage']];
    }

    private static function floatOf(mixed $value): float
    {
        return $value === null
            ? self::FALLBACK_MINIMUM_PERCENTAGE
            : round((float) $value, 2);
    }
}
