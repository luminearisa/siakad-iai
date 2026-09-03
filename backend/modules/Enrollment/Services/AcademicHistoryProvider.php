<?php

namespace Modules\Enrollment\Services;

use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Student\Models\Student;

class AcademicHistoryProvider
{
    /**
     * Get list of course IDs passed by the student in previous semesters.
     * In Phase 3, this checks finalized/approved enrollments from previous semesters.
     * In Phase 4 (Grade Module), this can be easily plugged into actual grade records.
     */
    public function getPassedCourseIds(Student $student, ?int $currentSemesterId = null): array
    {
        return StudentEnrollmentItem::whereHas('enrollment', function ($q) use ($student, $currentSemesterId) {
            $q->where('student_id', $student->id)
              ->whereIn('status', ['approved', 'locked'])
              ->when($currentSemesterId, fn($sq) => $sq->where('semester_id', '!=', $currentSemesterId));
        })
        ->where('status', 'enrolled')
        ->pluck('course_id')
        ->unique()
        ->toArray();
    }

    /**
     * Check if a student has met all prerequisites for a given course.
     */
    public function hasPassedPrerequisites(Student $student, int $courseId, ?int $currentSemesterId = null): bool
    {
        $course = \Modules\Course\Models\Course::with('prerequisites')->find($courseId);
        if (!$course || $course->prerequisites->isEmpty()) {
            return true;
        }

        $passedCourseIds = $this->getPassedCourseIds($student, $currentSemesterId);
        $requiredPrereqIds = $course->prerequisites->pluck('id')->toArray();

        foreach ($requiredPrereqIds as $reqId) {
            if (!in_array($reqId, $passedCourseIds, true)) {
                return false;
            }
        }

        return true;
    }
}
