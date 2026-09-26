<?php

namespace Modules\MBKM\Services;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\MbkmAttendanceStatus;
use Modules\MBKM\Models\MbkmAttendance;
use Modules\MBKM\Models\MbkmParticipant;

/**
 * MBKM attendance.
 *
 * Deliberately a separate boundary from the regular lecture attendance engine
 * (different entity, different lifecycle: a participant attends an activity, not
 * a class meeting). Duplicate rows for the same participant/date are prevented at
 * the database level and re-checked here.
 */
class MbkmAttendanceService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
    ) {}

    /**
     * Record (or update) attendance for one participant on one date.
     */
    public function record(MbkmParticipant $participant, array $data, User $actor): MbkmAttendance
    {
        $date = $data['attendance_date'];

        $attendance = MbkmAttendance::firstOrNew([
            'participant_id' => $participant->id,
            'attendance_date' => $date,
        ]);

        $isNew = !$attendance->exists;

        $attendance->fill([
            'status' => $data['status'],
            'check_in_time' => $data['check_in_time'] ?? null,
            'check_out_time' => $data['check_out_time'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        $attendance->save();

        $this->historyService->record(
            entity: $participant,
            action: $isNew ? 'attendance.created' : 'attendance.updated',
            notes: 'Presensi MBKM ' . $date . ' dicatat: ' . $data['status'] . '.',
            meta: ['attendance_id' => $attendance->id, 'date' => $date, 'status' => $data['status']],
            actorId: $actor->id
        );

        return $attendance;
    }

    /**
     * Batch record attendance rows (sheet-style input).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function recordBatch(MbkmParticipant $participant, array $rows, User $actor): array
    {
        $recorded = [];

        foreach ($rows as $row) {
            if (empty($row['attendance_date']) || empty($row['status'])) {
                continue;
            }

            $recorded[] = $this->record($participant, $row, $actor);
        }

        return $recorded;
    }

    /**
     * Attendance recap: totals per status and the attendance percentage.
     *
     * @return array<string, int|float|null>
     */
    public function summary(MbkmParticipant $participant): array
    {
        $rows = $participant->attendances()->get();

        $counts = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'sick' => 0,
            'absent' => 0,
        ];

        foreach ($rows as $row) {
            $value = $row->status instanceof MbkmAttendanceStatus ? $row->status->value : (string) $row->status;
            if (array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }

        $total = $rows->count();
        $attended = $counts['present'] + $counts['late'];

        return [
            'total_days' => $total,
            'present' => $counts['present'],
            'late' => $counts['late'],
            'excused' => $counts['excused'],
            'sick' => $counts['sick'],
            'absent' => $counts['absent'],
            'total_hours' => round((float) $rows->sum('duration_hours'), 2),
            'attendance_percentage' => $total > 0 ? round(($attended / $total) * 100, 2) : null,
        ];
    }

    /**
     * Validate attendance against the program's minimum attendance policy.
     */
    public function meetsMinimum(MbkmParticipant $participant): bool
    {
        $participant->loadMissing('program');

        $minimum = $participant->program?->min_attendance_percentage;

        if ($minimum === null) {
            return true;
        }

        $summary = $this->summary($participant);
        $percentage = $summary['attendance_percentage'];

        return $percentage !== null && $percentage >= (float) $minimum;
    }

    /**
     * Guard used by controllers: attendance only for valid participants.
     */
    public function assertParticipantAcceptsAttendance(MbkmParticipant $participant): void
    {
        if (!$participant->exists) {
            throw ValidationException::withMessages([
                'participant' => ['Peserta tidak valid.'],
            ]);
        }
    }
}
