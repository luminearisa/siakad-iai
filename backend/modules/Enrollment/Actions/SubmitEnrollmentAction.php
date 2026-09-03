<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;

class SubmitEnrollmentAction
{
    public function execute(StudentEnrollment $enrollment): StudentEnrollment
    {
        if (!in_array($enrollment->status, [EnrollmentStatus::DRAFT, EnrollmentStatus::REVISION_REQUIRED])) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot submit enrollment currently in '{$enrollment->status->value}' status."],
            ]);
        }

        if ($enrollment->items()->where('status', 'enrolled')->count() === 0) {
            throw ValidationException::withMessages([
                'enrollment' => ['Cannot submit an empty KRS. Please add at least one course class.'],
            ]);
        }

        $oldStatus = $enrollment->status->value;
        $enrollment->status = EnrollmentStatus::SUBMITTED;
        $enrollment->submitted_at = now();
        $enrollment->save();

        AuditService::log(
            action: 'submitted',
            module: 'Enrollment',
            description: "Enrollment #{$enrollment->id} ({$enrollment->total_credits} SKS) submitted for approval.",
            entity: $enrollment,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => EnrollmentStatus::SUBMITTED->value, 'submitted_at' => $enrollment->submitted_at->toISOString()]
        );

        return $enrollment->fresh(['student', 'semester', 'items.academicClass.course']);
    }
}
