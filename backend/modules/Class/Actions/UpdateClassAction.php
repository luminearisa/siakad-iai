<?php

namespace Modules\Class\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;

class UpdateClassAction
{
    public function execute(AcademicClass $class, array $data): AcademicClass
    {
        $oldValues = $class->toArray();
        $classData = collect($data)->except(['lecturers'])->toArray();

        $class->update($classData);

        if (isset($data['lecturers'])) {
            $syncData = [];
            foreach ($data['lecturers'] as $lecturer) {
                $lecturerId = is_array($lecturer) ? $lecturer['lecturer_id'] : $lecturer;
                $role = is_array($lecturer) ? ($lecturer['role'] ?? 'primary') : 'primary';
                $syncData[$lecturerId] = ['role' => $role];
            }
            $class->lecturers()->sync($syncData);
        }

        AuditService::log(
            action: 'updated',
            module: 'Class',
            description: "Class {$class->code} was updated.",
            entity: $class,
            oldValues: $oldValues,
            newValues: $class->fresh()->toArray()
        );

        return $class->fresh(['course', 'semester.academicYear', 'studyProgram', 'lecturers']);
    }
}
