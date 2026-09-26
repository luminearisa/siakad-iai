<?php

namespace Modules\Schedule\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Services\ScheduleConflictService;

class UpdateScheduleAction
{
    public function __construct(
        protected ScheduleConflictService $conflictService,
        protected SyncClassSessionsAction $sessionSync
    ) {}

    public function execute(ClassSchedule $schedule, array $data): ClassSchedule
    {
        $oldValues = $schedule->toArray();
        $class = $schedule->academicClass()->with('lecturers')->first();

        $previousClassId = $schedule->class_id;

        $dayOfWeek = $data['day_of_week'] ?? $schedule->day_of_week;
        $startTime = $data['start_time'] ?? $schedule->start_time;
        $endTime = $data['end_time'] ?? $schedule->end_time;
        $roomId = array_key_exists('room_id', $data) ? $data['room_id'] : $schedule->room_id;

        $this->conflictService->validateScheduleConflict(
            class: $class,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            roomId: $roomId,
            excludeScheduleId: $schedule->id
        );

        $schedule->update($data);

        // Nomor pertemuan global per kelas, jadi pindah kelas mengharuskan
        // sinkronisasi kelas lama sekaligus kelas baru. Sesi yang sudah
        // berlangsung / sudah punya presensi tidak disentuh oleh sync.
        $syncedSessions = 0;
        if ((int) $schedule->class_id !== (int) $previousClassId) {
            $previousClass = AcademicClass::find($previousClassId);
            if ($previousClass) {
                $syncedSessions += $this->sessionSync->syncClass($previousClass);
            }
        }
        $syncedSessions += $this->sessionSync->syncSchedule($schedule);

        AuditService::log(
            action: 'updated',
            module: 'Schedule',
            description: "Schedule #{$schedule->id} was updated. {$syncedSessions} teaching session(s) synced.",
            entity: $schedule,
            oldValues: $oldValues,
            newValues: $schedule->fresh()->toArray()
        );

        return $schedule->fresh(['academicClass.course', 'room']);
    }
}
