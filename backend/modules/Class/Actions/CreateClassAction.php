<?php

namespace Modules\Class\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;

class CreateClassAction
{
    public function execute(array $data): AcademicClass
    {
        if (!isset($data['status'])) {
            $data['status'] = ClassStatus::DRAFT;
        }

        if (empty($data['name'])) {
            $course = Course::find($data['course_id']);
            $section = $data['section'] ?? 'A';
            $data['name'] = ($course ? $course->name : 'Class') . " - {$section}";
        }

        $class = AcademicClass::create($data);

        if (!empty($data['lecturers'])) {
            foreach ($data['lecturers'] as $lecturer) {
                $lecturerId = is_array($lecturer) ? $lecturer['lecturer_id'] : $lecturer;
                $role = is_array($lecturer) ? ($lecturer['role'] ?? 'primary') : 'primary';
                $class->classLecturers()->create([
                    'lecturer_id' => $lecturerId,
                    'role' => $role,
                ]);
            }
        }

        AuditService::log(
            action: 'created',
            module: 'Class',
            description: "Class {$class->code} ({$class->name}) was created.",
            entity: $class,
            oldValues: null,
            newValues: $class->toArray()
        );

        return $class->load(['course', 'semester.academicYear', 'studyProgram', 'lecturers']);
    }
}
