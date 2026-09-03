<?php

namespace Modules\Student\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class ChangeStudentStatusAction
{
    public function execute(Student $student, StudentStatus|string $status, ?string $notes = null): Student
    {
        $oldStatus = $student->status instanceof \BackedEnum ? $student->status->value : $student->status;
        $newStatus = $status instanceof StudentStatus ? $status : StudentStatus::from($status);

        $student->status = $newStatus;

        if ($notes !== null) {
            $student->notes = ($student->notes ? $student->notes . "\n" : '') . "[Status Change to {$newStatus->value}]: {$notes}";
        }

        if ($newStatus === StudentStatus::GRADUATED && !$student->graduation_date) {
            $student->graduation_date = now();
        }

        $student->save();

        AuditService::log(
            action: 'status_changed',
            module: 'Student',
            description: "Student {$student->full_name} status changed from {$oldStatus} to {$newStatus->value}.",
            entity: $student,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus->value, 'notes' => $notes]
        );

        return $student;
    }
}
