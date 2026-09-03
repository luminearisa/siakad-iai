<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;

class RequestRevisionAction
{
    public function execute(StudentEnrollment $enrollment, int $userId, string $revisionNotes): StudentEnrollment
    {
        if (!in_array($enrollment->status, [EnrollmentStatus::SUBMITTED, EnrollmentStatus::APPROVED])) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot request revision for enrollment in '{$enrollment->status->value}' status."],
            ]);
        }

        $oldStatus = $enrollment->status->value;
        $enrollment->status = EnrollmentStatus::REVISION_REQUIRED;
        $enrollment->notes = ($enrollment->notes ? $enrollment->notes . "\n" : '') . "[Revision Requested by user #{$userId}]: {$revisionNotes}";
        $enrollment->save();

        AuditService::log(
            action: 'revision_requested',
            module: 'Enrollment',
            description: "Revision requested for enrollment #{$enrollment->id}: {$revisionNotes}",
            entity: $enrollment,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => EnrollmentStatus::REVISION_REQUIRED->value, 'revision_notes' => $revisionNotes]
        );

        return $enrollment->fresh(['student', 'semester', 'items.academicClass.course']);
    }
}
