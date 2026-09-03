<?php

namespace Modules\Class\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Class\Models\ClassLecturer;

class AssignClassLecturerAction
{
    public function assign(AcademicClass $class, int $lecturerId, string $role = 'primary'): ClassLecturer
    {
        $classLecturer = ClassLecturer::updateOrCreate(
            ['class_id' => $class->id, 'lecturer_id' => $lecturerId],
            ['role' => $role]
        );

        AuditService::log(
            action: 'lecturer_assigned',
            module: 'Class',
            description: "Lecturer #{$lecturerId} assigned to class {$class->code} as {$role}.",
            entity: $classLecturer,
            oldValues: null,
            newValues: $classLecturer->toArray()
        );

        return $classLecturer->load('lecturer');
    }

    public function remove(AcademicClass $class, int $lecturerId): bool
    {
        $record = ClassLecturer::where('class_id', $class->id)->where('lecturer_id', $lecturerId)->first();
        if ($record) {
            $oldValues = $record->toArray();
            $record->delete();

            AuditService::log(
                action: 'lecturer_removed',
                module: 'Class',
                description: "Lecturer #{$lecturerId} removed from class {$class->code}.",
                entity: null,
                oldValues: $oldValues,
                newValues: null
            );
            return true;
        }

        return false;
    }
}
