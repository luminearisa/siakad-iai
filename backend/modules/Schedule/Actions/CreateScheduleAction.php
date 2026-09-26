<?php

namespace Modules\Schedule\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Services\ScheduleConflictService;

class CreateScheduleAction
{
    public function __construct(
        protected ScheduleConflictService $conflictService,
        protected SyncClassSessionsAction $sessionSync
    ) {}

    public function execute(array $data): ClassSchedule
    {
        $class = AcademicClass::with('lecturers')->findOrFail($data['class_id']);

        // Check conflicts before creating schedule
        $this->conflictService->validateScheduleConflict(
            class: $class,
            dayOfWeek: $data['day_of_week'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            roomId: $data['room_id'] ?? null
        );

        $schedule = ClassSchedule::create($data);

        // Sesi perkuliahan selalu dibangun ulang dari seluruh jadwal kelas supaya
        // nomor pertemuan tetap unik per kelas (lihat SyncClassSessionsAction).
        $syncedSessions = $this->sessionSync->syncClass($class);

        AuditService::log(
            action: 'created',
            module: 'Schedule',
            description: "Schedule created for class {$class->code} on {$schedule->day_of_week->value} ({$schedule->start_time} - {$schedule->end_time}). {$syncedSessions} teaching session(s) synced.",
            entity: $schedule,
            oldValues: null,
            newValues: $schedule->toArray()
        );

        return $schedule->load(['academicClass.course', 'room']);
    }
}
