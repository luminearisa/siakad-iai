<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;

class LockEnrollmentAction
{
    public function execute(StudentEnrollment $enrollment, int $userId): StudentEnrollment
    {
        if ($enrollment->status !== EnrollmentStatus::APPROVED) {
            throw ValidationException::withMessages([
                'enrollment' => ["Only approved enrollments can be locked (current status: {$enrollment->status->value})."],
            ]);
        }

        $oldStatus = $enrollment->status->value;
        $enrollment->status = EnrollmentStatus::LOCKED;
        $enrollment->save();

        AuditService::log(
            action: 'locked',
            module: 'Enrollment',
            description: "Enrollment #{$enrollment->id} locked by user #{$userId}.",
            entity: $enrollment,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => EnrollmentStatus::LOCKED->value]
        );

        return $enrollment->fresh(['student', 'semester', 'approver', 'items.academicClass.course']);
    }
}
