<?php

namespace Modules\Course\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Course\Models\Course;

class SetCoursePrerequisitesAction
{
    public function execute(Course $course, array $prerequisites): Course
    {
        $oldPrereqs = $course->prerequisites->pluck('id')->toArray();
        $syncData = [];

        foreach ($prerequisites as $prereq) {
            $id = is_array($prereq) ? $prereq['course_id'] : $prereq;
            $minGrade = is_array($prereq) ? ($prereq['minimum_grade'] ?? null) : null;
            $syncData[$id] = ['minimum_grade' => $minGrade];
        }

        $course->prerequisites()->sync($syncData);

        AuditService::log(
            action: 'prerequisites_updated',
            module: 'Course',
            description: "Prerequisites updated for course {$course->code}.",
            entity: $course,
            oldValues: ['prerequisite_ids' => $oldPrereqs],
            newValues: ['prerequisites' => $prerequisites]
        );

        return $course->load(['prerequisites', 'dependents']);
    }
}
