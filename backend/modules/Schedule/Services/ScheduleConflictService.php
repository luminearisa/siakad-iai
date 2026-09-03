<?php

namespace Modules\Schedule\Services;

use Illuminate\Validation\ValidationException;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Models\ClassSchedule;

class ScheduleConflictService
{
    /**
     * Determine if two time periods on the same day overlap.
     * Standard interval overlap: startA < endB && endA > startB.
     */
    public static function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return ($startA < $endB) && ($endA > $startB);
    }

    /**
     * Validate schedule conflict for Room, Lecturer, and Class before saving.
     *
     * @throws ValidationException
     */
    public function validateScheduleConflict(
        AcademicClass $class,
        DayOfWeek|string $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $roomId = null,
        ?int $excludeScheduleId = null
    ): void {
        $dayValue = $dayOfWeek instanceof DayOfWeek ? $dayOfWeek->value : $dayOfWeek;

        // 1. Check Class self-conflict (Class cannot have 2 overlapping schedules)
        $classConflict = ClassSchedule::where('class_id', $class->id)
            ->where('status', 'active')
            ->where('day_of_week', $dayValue)
            ->when($excludeScheduleId, fn($q) => $q->where('id', '!=', $excludeScheduleId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->first();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'schedule' => ["This class already has a conflicting schedule on {$dayValue} between {$classConflict->start_time} and {$classConflict->end_time}."],
            ]);
        }

        // 2. Check Room conflict
        if ($roomId) {
            $roomConflict = ClassSchedule::where('room_id', $roomId)
                ->where('status', 'active')
                ->where('day_of_week', $dayValue)
                ->when($excludeScheduleId, fn($q) => $q->where('id', '!=', $excludeScheduleId))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->whereHas('academicClass', function ($q) use ($class) {
                    $q->where('semester_id', $class->semester_id);
                })
                ->with('academicClass.course', 'room')
                ->first();

            if ($roomConflict) {
                $roomName = $roomConflict->room?->name ?? "Room #{$roomId}";
                $conflictClassName = $roomConflict->academicClass?->name ?? "Class #{$roomConflict->class_id}";
                throw ValidationException::withMessages([
                    'room_id' => ["Room {$roomName} is already occupied by {$conflictClassName} on {$dayValue} ({$roomConflict->start_time} - {$roomConflict->end_time})."],
                ]);
            }
        }

        // 3. Check Lecturer conflict
        $lecturerIds = $class->lecturers->pluck('id')->toArray();
        if (!empty($lecturerIds)) {
            $lecturerConflict = ClassSchedule::where('status', 'active')
                ->where('day_of_week', $dayValue)
                ->when($excludeScheduleId, fn($q) => $q->where('id', '!=', $excludeScheduleId))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->whereHas('academicClass', function ($q) use ($class, $lecturerIds) {
                    $q->where('semester_id', $class->semester_id)
                      ->where('id', '!=', $class->id)
                      ->whereHas('lecturers', function ($lq) use ($lecturerIds) {
                          $lq->whereIn('lecturers.id', $lecturerIds);
                      });
                })
                ->with('academicClass.lecturers')
                ->first();

            if ($lecturerConflict) {
                $lecturer = $lecturerConflict->academicClass->lecturers->whereIn('id', $lecturerIds)->first();
                $lecturerName = $lecturer?->full_name ?? 'Assigned Lecturer';
                throw ValidationException::withMessages([
                    'lecturer' => ["Lecturer {$lecturerName} has a scheduling conflict on {$dayValue} ({$lecturerConflict->start_time} - {$lecturerConflict->end_time}) in {$lecturerConflict->academicClass->name}."],
                ]);
            }
        }
    }

    /**
     * Check if a proposed class has schedule conflicts with a student's already selected classes.
     *
     * @throws ValidationException
     */
    public function validateStudentScheduleConflict(StudentEnrollment $enrollment, AcademicClass $proposedClass): void
    {
        $proposedSchedules = $proposedClass->schedules()->where('status', 'active')->get();
        if ($proposedSchedules->isEmpty()) {
            return;
        }

        // Retrieve schedules of all classes currently enrolled by the student in this enrollment
        $existingClassIds = $enrollment->items()
            ->where('status', 'enrolled')
            ->pluck('class_id')
            ->toArray();

        if (empty($existingClassIds)) {
            return;
        }

        $existingSchedules = ClassSchedule::whereIn('class_id', $existingClassIds)
            ->where('status', 'active')
            ->with('academicClass.course')
            ->get();

        foreach ($proposedSchedules as $pSched) {
            $pDay = $pSched->day_of_week instanceof DayOfWeek ? $pSched->day_of_week->value : $pSched->day_of_week;

            foreach ($existingSchedules as $eSched) {
                $eDay = $eSched->day_of_week instanceof DayOfWeek ? $eSched->day_of_week->value : $eSched->day_of_week;

                if ($pDay === $eDay) {
                    if (self::timesOverlap($pSched->start_time, $pSched->end_time, $eSched->start_time, $eSched->end_time)) {
                        $conflictCourse = $eSched->academicClass?->course?->name ?? $eSched->academicClass?->name;
                        throw ValidationException::withMessages([
                            'class_id' => ["Schedule conflict on {$pDay} ({$pSched->start_time} - {$pSched->end_time}) with already enrolled course: {$conflictCourse} ({$eSched->start_time} - {$eSched->end_time})."],
                        ]);
                    }
                }
            }
        }
    }
}
