<?php

namespace Modules\Course\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Course\Enums\CourseStatus;
use Modules\Course\Models\Course;

class CreateCourseAction
{
    public function execute(array $data): Course
    {
        $prerequisites = $data['prerequisites'] ?? [];
        $courseData = collect($data)->except(['prerequisites'])->toArray();

        if (!isset($courseData['status'])) {
            $courseData['status'] = CourseStatus::ACTIVE;
        }

        // Auto calculate total credits if omitted
        if (!isset($courseData['credits'])) {
            $courseData['credits'] = ($courseData['theory_credits'] ?? 0) + ($courseData['practical_credits'] ?? 0);
        }

        $course = Course::create($courseData);

        if (!empty($prerequisites)) {
            $syncData = [];
            foreach ($prerequisites as $prereq) {
                $id = is_array($prereq) ? $prereq['course_id'] : $prereq;
                $minGrade = is_array($prereq) ? ($prereq['minimum_grade'] ?? null) : null;
                $syncData[$id] = ['minimum_grade' => $minGrade];
            }
            $course->prerequisites()->sync($syncData);
        }

        AuditService::log(
            action: 'created',
            module: 'Course',
            description: "Course {$course->code} - {$course->name} was created.",
            entity: $course,
            oldValues: null,
            newValues: $course->toArray()
        );

        return $course->load(['prerequisites', 'dependents']);
    }
}
