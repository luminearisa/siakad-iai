<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\EnrollmentStatusHistory;
use Modules\Enrollment\Services\EnrollmentValidationService;

class SubmitEnrollmentAction
{
    public function __construct(
        protected EnrollmentValidationService $validationService
    ) {}

    public function execute(StudentEnrollment $enrollment): StudentEnrollment
    {
        if (!in_array($enrollment->status, [EnrollmentStatus::DRAFT, EnrollmentStatus::REVISION_REQUIRED])) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot submit enrollment currently in '{$enrollment->status->value}' status."],
            ]);
        }

        // The registration window is re-validated at submit time, not only while
        // adding classes: a draft assembled inside the window must not be submittable
        // after the KRS/KPRS deadline has passed.
        $windowErrors = $this->validationService->checkKrsWindow($enrollment);
        if (!empty($windowErrors)) {
            throw ValidationException::withMessages($windowErrors);
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

        EnrollmentStatusHistory::record(
            enrollment: $enrollment,
            action: 'submitted',
            fromStatus: $oldStatus,
            toStatus: EnrollmentStatus::SUBMITTED->value,
            notes: 'KRS diajukan oleh mahasiswa untuk persetujuan Dosen Pembimbing Akademik.'
        );

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
