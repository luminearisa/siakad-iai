<?php

namespace Modules\Schedule\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Enums\ScheduleStatus;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Services\ScheduleConflictService;

class CreateScheduleAction
{
    public function __construct(
        protected ScheduleConflictService $conflictService
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

        // Auto-generate teaching sessions for the class
        $this->generateTeachingSessions($schedule, $class);

        AuditService::log(
            action: 'created',
            module: 'Schedule',
            description: "Schedule created for class {$class->code} on {$schedule->day_of_week->value} ({$schedule->start_time} - {$schedule->end_time}).",
            entity: $schedule,
            oldValues: null,
            newValues: $schedule->toArray()
        );

        return $schedule->load(['academicClass.course', 'room']);
    }

    protected function generateTeachingSessions(ClassSchedule $schedule, AcademicClass $class): void
    {
        $lecturerId = $class->lecturers->first()?->id
            ?? \Modules\Lecturer\Models\Lecturer::where('status', 'active')->value('id')
            ?? \Modules\Lecturer\Models\Lecturer::value('id');

        if (!$lecturerId) {
            return;
        }

        // Determine base start date from semester, or current week
        $semester = $class->semester;
        $baseDate = $semester?->start_date ? \Carbon\Carbon::parse($semester->start_date) : now();

        $dayValue = $schedule->day_of_week instanceof \BackedEnum ? $schedule->day_of_week->value : $schedule->day_of_week;

        $carbonDay = match (strtolower((string) $dayValue)) {
            'monday', 'senin' => \Carbon\CarbonInterface::MONDAY,
            'tuesday', 'selasa' => \Carbon\CarbonInterface::TUESDAY,
            'wednesday', 'rabu' => \Carbon\CarbonInterface::WEDNESDAY,
            'thursday', 'kamis' => \Carbon\CarbonInterface::THURSDAY,
            'friday', 'jumat' => \Carbon\CarbonInterface::FRIDAY,
            'saturday', 'sabtu' => \Carbon\CarbonInterface::SATURDAY,
            'sunday', 'minggu' => \Carbon\CarbonInterface::SUNDAY,
            default => \Carbon\CarbonInterface::MONDAY,
        };

        $sessionDate = $baseDate->copy();
        if ($sessionDate->dayOfWeek !== $carbonDay) {
            $sessionDate->next($carbonDay);
        }

        $totalMeetings = 16;
        for ($meetingNumber = 1; $meetingNumber <= $totalMeetings; $meetingNumber++) {
            \Modules\Attendance\Models\TeachingSession::updateOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'meeting_number' => $meetingNumber,
                ],
                [
                    'schedule_id' => $schedule->id,
                    'lecturer_id' => $lecturerId,
                    'session_date' => $sessionDate->format('Y-m-d'),
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'room_id' => $schedule->room_id,
                    'teaching_method' => 'offline',
                    'status' => 'scheduled',
                    'topic' => "Pertemuan ke-{$meetingNumber}",
                ]
            );

            $sessionDate->addWeek();
        }
    }
}
