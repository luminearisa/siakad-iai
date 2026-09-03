<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;

class RejectEnrollmentAction
{
    public function execute(StudentEnrollment $enrollment, int $userId, string $reason): StudentEnrollment
    {
        if ($enrollment->status !== EnrollmentStatus::SUBMITTED) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot reject enrollment currently in '{$enrollment->status->value}' status."],
            ]);
        }

        $oldStatus = $enrollment->status->value;
        $enrollment->status = EnrollmentStatus::REJECTED;
        $enrollment->notes = ($enrollment->notes ? $enrollment->notes . "\n" : '') . "[Rejected by user #{$userId}]: {$reason}";
        $enrollment->save();

        AuditService::log(
            action: 'rejected',
            module: 'Enrollment',
            description: "Enrollment #{$enrollment->id} was rejected. Reason: {$reason}",
            entity: $enrollment,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => EnrollmentStatus::REJECTED->value, 'reason' => $reason]
        );

        return $enrollment->fresh(['student', 'semester', 'items.academicClass.course']);
    }
}
