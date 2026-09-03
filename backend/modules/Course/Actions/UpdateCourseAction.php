<?php

namespace Modules\Course\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Course\Models\Course;

class UpdateCourseAction
{
    public function execute(Course $course, array $data): Course
    {
        $oldValues = $course->toArray();
        $prerequisites = $data['prerequisites'] ?? null;
        $courseData = collect($data)->except(['prerequisites'])->toArray();

        $course->update($courseData);

        if ($prerequisites !== null) {
            $syncData = [];
            foreach ($prerequisites as $prereq) {
                $id = is_array($prereq) ? $prereq['course_id'] : $prereq;
                $minGrade = is_array($prereq) ? ($prereq['minimum_grade'] ?? null) : null;
                $syncData[$id] = ['minimum_grade' => $minGrade];
            }
            $course->prerequisites()->sync($syncData);
        }

        AuditService::log(
            action: 'updated',
            module: 'Course',
            description: "Course {$course->code} - {$course->name} was updated.",
            entity: $course,
            oldValues: $oldValues,
            newValues: $course->fresh()->toArray()
        );

        return $course->fresh(['prerequisites', 'dependents']);
    }
}
