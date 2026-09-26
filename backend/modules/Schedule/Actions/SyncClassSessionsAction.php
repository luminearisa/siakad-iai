<?php

namespace Modules\Schedule\Actions;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Enums\ScheduleStatus;
use Modules\Schedule\Models\ClassSchedule;

/**
 * Membangun ulang TeachingSession sebuah kelas dari SELURUH jadwalnya.
 *
 * Nomor pertemuan bersifat GLOBAL per kelas: seluruh occurrence (jadwal x minggu
 * kuliah) diurutkan berdasarkan (tanggal, jam mulai) lalu diberi nomor 1..N.
 * Kelas dengan dua jadwal per minggu karenanya mendapat pertemuan 1..2N, bukan
 * dua rangkaian 1..N yang saling menimpa pada kunci unik
 * (academic_class_id, meeting_number).
 */
class SyncClassSessionsAction
{
    /** Kolom meeting_number bertipe unsignedTinyInteger. */
    private const MAX_MEETING_NUMBER = 255;

    /** Fallback bila semester tidak mengisi total_teaching_weeks. */
    private const DEFAULT_TEACHING_WEEKS = 16;

    /**
     * @return int Jumlah sesi yang dibuat atau diubah.
     */
    public function syncClass(AcademicClass $class): int
    {
        $class->loadMissing(['semester', 'lecturers']);

        // Tanpa pengampu sesi tidak boleh dibuat: menebak dosen aktif lain akan
        // menempatkan sesi pada dosen yang tidak mengajar kelas tersebut.
        $lecturerId = $class->lecturers->first()?->id;
        if (! $lecturerId) {
            return 0;
        }

        $occurrences = $this->collectOccurrences($class);
        if ($occurrences->isEmpty()) {
            return 0;
        }

        $existing = TeachingSession::where('academic_class_id', $class->id)->get()->keyBy('meeting_number');

        $changed = 0;
        foreach ($occurrences as $index => $occurrence) {
            $meetingNumber = $index + 1;
            if ($meetingNumber > self::MAX_MEETING_NUMBER) {
                break;
            }

            /** @var TeachingSession|null $session */
            $session = $existing->get($meetingNumber);
            if ($session && $this->isProtected($session)) {
                continue; // nomor lama dibiarkan; sesi sisa tidak pernah dihapus
            }

            $schedule = $occurrence['schedule'];
            $attributes = [
                'schedule_id' => $schedule->id,
                'lecturer_id' => $lecturerId,
                'session_date' => $occurrence['date']->toDateString(),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'room_id' => $schedule->room_id,
                'teaching_method' => 'offline',
                'status' => 'scheduled',
                'topic' => $this->resolveTopic($session, $meetingNumber),
            ];

            if ($session) {
                if ($session->fill($attributes)->isDirty()) {
                    $session->save();
                    $changed++;
                }
            } else {
                TeachingSession::create($attributes + [
                    'academic_class_id' => $class->id,
                    'meeting_number' => $meetingNumber,
                ]);

                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Sinkronisasi satu jadwal. Karena penomoran bersifat global per kelas,
     * perubahan hari/jam satu jadwal ikut menggeser nomor sesi jadwal lain,
     * sehingga himpunan sesi kelas tetap dibangun ulang seluruhnya.
     *
     * @return int Jumlah sesi yang dibuat atau diubah.
     */
    public function syncSchedule(ClassSchedule $schedule): int
    {
        $class = $schedule->academicClass()->with(['semester', 'lecturers'])->first();

        return $class ? $this->syncClass($class) : 0;
    }

    /**
     * Seluruh occurrence (jadwal x minggu kuliah) yang sudah terurut.
     *
     * @return Collection<int, array{schedule: ClassSchedule, date: Carbon}>
     */
    protected function collectOccurrences(AcademicClass $class): Collection
    {
        $semester = $class->semester;

        $totalWeeks = (int) ($semester?->total_teaching_weeks ?: self::DEFAULT_TEACHING_WEEKS);

        $rangeStart = $this->firstDate($semester?->lecture_start_date, $semester?->start_date)
            ?? now()->startOfDay();
        $rangeEnd = $this->firstDate($semester?->lecture_end_date, $semester?->end_date);

        // Minggu ke-1 adalah minggu (anchor Senin) tempat perkuliahan dimulai,
        // supaya semua jadwal di kelas yang sama berbagi grid minggu.
        $weekAnchor = $rangeStart->copy()->startOfDay()->startOfWeek();

        return $class->schedules()
            // Jadwal yang dibatalkan tidak boleh menyumbang pertemuan baru.
            ->where('status', '!=', ScheduleStatus::CANCELLED->value)
            ->get()
            ->flatMap(function (ClassSchedule $schedule) use ($weekAnchor, $rangeStart, $rangeEnd, $totalWeeks) {
                $carbonDay = $this->carbonDay($schedule->day_of_week);
                if ($carbonDay === null) {
                    return [];
                }

                $dates = [];
                for ($week = 0; $week < $totalWeeks; $week++) {
                    // Minggu ber-anchor Senin: Minggu (0) dihitung sebagai hari ke-7.
                    $date = $weekAnchor->copy()
                        ->addWeeks($week)
                        ->addDays(($carbonDay + 6) % 7);

                    if ($date->lt($rangeStart->copy()->startOfDay())) {
                        continue; // hari jadwal jatuh sebelum perkuliahan dimulai
                    }
                    if ($rangeEnd && $date->gt($rangeEnd->copy()->endOfDay())) {
                        continue;
                    }
                    if ($schedule->effective_from && $date->lt($schedule->effective_from->copy()->startOfDay())) {
                        continue;
                    }
                    if ($schedule->effective_until && $date->gt($schedule->effective_until->copy()->endOfDay())) {
                        continue;
                    }

                    $dates[] = ['schedule' => $schedule, 'date' => $date];
                }

                return $dates;
            })
            ->sortBy(fn (array $occurrence) => $occurrence['date']->getTimestamp() * 1440
                + $this->minutesOf($occurrence['schedule']->start_time))
            ->values();
    }

    /**
     * Sesi yang tidak boleh disentuh ulang: sudah berlangsung, sudah punya
     * presensi terekam, atau statusnya sudah diubah (open/closed/cancelled).
     */
    protected function isProtected(TeachingSession $session): bool
    {
        $status = $session->status;

        if ($status === SessionStatus::CANCELLED) {
            return true;
        }

        if ($status && $status !== SessionStatus::SCHEDULED) {
            return true; // sesi sudah dibuka / ditutup manual oleh akademik
        }

        if ($session->session_date && $session->session_date->copy()->endOfDay()->isPast()) {
            return true;
        }

        return $session->attendances()->whereNotNull('recorded_at')->exists();
    }

    /**
     * "H:i" / "H:i:s" menjadi menit sejak tengah malam untuk keperluan pengurutan.
     */
    protected function minutesOf(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hour) * 60 + ((int) $minute);
    }

    /**
     * Topik bawaan boleh ditulis ulang, topik hasil isian manual dipertahankan.
     */
    protected function resolveTopic(?TeachingSession $session, int $meetingNumber): string
    {
        $generated = "Pertemuan ke-{$meetingNumber}";

        if ($session && $session->topic && ! preg_match('/^Pertemuan ke-\d+$/', $session->topic)) {
            return $session->topic;
        }

        return $generated;
    }

    /**
     * Hari enum/string (inggris atau indonesia) ke konstanta hari Carbon.
     */
    protected function carbonDay(DayOfWeek|string|null $day): ?int
    {
        $value = $day instanceof DayOfWeek ? $day->value : $day;

        return match (strtolower(trim((string) $value))) {
            'monday', 'senin' => CarbonInterface::MONDAY,
            'tuesday', 'selasa', 'sylasa' => CarbonInterface::TUESDAY,
            'wednesday', 'rabu' => CarbonInterface::WEDNESDAY,
            'thursday', 'kamis' => CarbonInterface::THURSDAY,
            'friday', 'jumat' => CarbonInterface::FRIDAY,
            'saturday', 'sabtu' => CarbonInterface::SATURDAY,
            'sunday', 'minggu' => CarbonInterface::SUNDAY,
            default => null,
        };
    }

    /**
     * Nilai tanggal pertama yang terisi.
     */
    protected function firstDate(mixed ...$values): ?Carbon
    {
        foreach ($values as $value) {
            if ($value instanceof Carbon) {
                return $value->copy();
            }
            if ($value) {
                return Carbon::parse($value)->startOfDay();
            }
        }

        return null;
    }
}
