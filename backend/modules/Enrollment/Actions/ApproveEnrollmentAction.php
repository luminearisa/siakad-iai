<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;

class ApproveEnrollmentAction
{
    public function execute(StudentEnrollment $enrollment, int $approverUserId, ?string $notes = null): StudentEnrollment
    {
        if ($enrollment->status !== EnrollmentStatus::SUBMITTED) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot approve enrollment currently in '{$enrollment->status->value}' status. Only submitted enrollments can be approved."],
            ]);
        }

        $oldStatus = $enrollment->status->value;
        $enrollment->status = EnrollmentStatus::APPROVED;
        $enrollment->approved_at = now();
        $enrollment->approved_by = $approverUserId;
        if ($notes) {
            $enrollment->notes = ($enrollment->notes ? $enrollment->notes . "\n" : '') . "[Approved]: {$notes}";
        }
        $enrollment->save();

        AuditService::log(
            action: 'approved',
            module: 'Enrollment',
            description: "Enrollment #{$enrollment->id} approved by user #{$approverUserId}.",
            entity: $enrollment,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => EnrollmentStatus::APPROVED->value, 'approved_by' => $approverUserId]
        );

        return $enrollment->fresh(['student', 'semester', 'approver', 'items.academicClass.course']);
    }
}
