<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\EnrollmentStatusHistory;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Enrollment\Services\EnrollmentValidationService;

/**
 * Remove a class from a KRS.
 *
 * Two distinct paths, because they mean different things academically:
 *
 *  1. The KRS is still editable (draft / revision_required): the row never became
 *     official study load, so it is transitioned to CANCELLED. Capacity is released
 *     and the credit total recomputed, exactly like a hard removal, but the row is
 *     kept so the draft stays traceable and the unique (enrollment_id, class_id)
 *     index can be reused when the student picks the class again.
 *
 *  2. The KRS is already approved/locked: this is BATAL-TAMBAH. The class is
 *     officially withdrawn, so the row is transitioned to DROPPED, stamped with
 *     `finalized_at`, the class seat and the enrollment's `total_credits` are
 *     released, and both a status-history entry and an audit-log entry are written.
 *     Nothing is ever destroyed: a withdrawn class must remain provable afterwards.
 */
class RemoveEnrollmentItemAction
{
    /**
     * `enrollments.lock` is the academic-office marker used across this module
     * (see EnrollmentController::lecturerScope()). Mahasiswa and plain dosen do not
     * hold it, so they cannot withdraw a class from an already approved KRS on
     * their own — that has to go through the office or the student's DPA workflow.
     */
    protected const BATAL_TAMBAH_PERMISSION = 'enrollments.lock';

    public function __construct(
        protected EnrollmentValidationService $validationService
    ) {}

    public function execute(StudentEnrollment $enrollment, StudentEnrollmentItem $item, ?string $reason = null): bool
    {
        if ($item->enrollment_id !== $enrollment->id) {
            throw ValidationException::withMessages([
                'item' => ['Item does not belong to this enrollment.'],
            ]);
        }

        if ($item->isInactive()) {
            throw ValidationException::withMessages([
                'item' => ["Mata kuliah ini sudah dibatalkan (status: {$item->status->value}) dan tidak lagi menjadi bagian dari KRS."],
            ]);
        }

        $isEditable = $enrollment->isEditable();
        $isBatalTambah = in_array($enrollment->status, [EnrollmentStatus::APPROVED, EnrollmentStatus::LOCKED], true);

        if (!$isEditable && !$isBatalTambah) {
            throw ValidationException::withMessages([
                'enrollment' => ["Tidak dapat mengubah KRS dengan status '{$enrollment->status->value}'."],
            ]);
        }

        if ($isBatalTambah) {
            $this->authorizeBatalTambah($enrollment);
        }

        // The KRS/KPRS window guards removals too: a class may not be dropped from a
        // draft once the registration deadline has passed (KPRS still applies).
        $windowErrors = $this->validationService->checkKrsWindow($enrollment);
        if (!empty($windowErrors)) {
            throw ValidationException::withMessages($windowErrors);
        }

        return DB::transaction(function () use ($enrollment, $item, $reason, $isBatalTambah) {
            $classId = $item->class_id;
            $oldValues = $item->toArray();

            // Lock class row so the seat release cannot race with another add.
            $class = AcademicClass::where('id', $classId)->lockForUpdate()->first();

            $targetStatus = $isBatalTambah
                ? EnrollmentItemStatus::DROPPED
                : EnrollmentItemStatus::CANCELLED;

            $item->transitionTo($targetStatus, finalized: $isBatalTambah);

            if ($class && $class->enrolled_count > 0) {
                $class->decrement('enrolled_count');
            }

            // Recomputes total_credits from ENROLLED rows only, so the dropped class
            // immediately stops counting towards the student's study load.
            $enrollment->recalculateCredits();

            EnrollmentStatusHistory::record(
                enrollment: $enrollment,
                action: $isBatalTambah ? 'item_dropped' : 'item_cancelled',
                fromStatus: EnrollmentItemStatus::ENROLLED->value,
                toStatus: $targetStatus->value,
                notes: $isBatalTambah
                    ? trim('Batal-tambah: mata kuliah ditarik dari KRS yang sudah disetujui.' . ($reason ? " Alasan: {$reason}" : ''))
                    : trim('Mata kuliah dibatalkan saat KRS masih berstatus draf.' . ($reason ? " Alasan: {$reason}" : '')),
                item: $item,
                meta: [
                    'class_id' => $classId,
                    'course_id' => $item->course_id,
                    'credits' => $item->credits,
                    'enrollment_status' => $enrollment->status instanceof EnrollmentStatus ? $enrollment->status->value : $enrollment->status,
                    'total_credits_after' => (int) $enrollment->total_credits,
                ],
            );

            AuditService::log(
                action: $isBatalTambah ? 'item_dropped' : 'item_removed',
                module: 'Enrollment',
                description: $isBatalTambah
                    ? "Batal-tambah: class item #{$classId} dropped from approved/locked KRS #{$enrollment->id} ({$item->credits} SKS released)."
                    : "Class item #{$classId} removed from KRS #{$enrollment->id}.",
                entity: $item,
                oldValues: $oldValues,
                newValues: $item->toArray()
            );

            return true;
        });
    }

    /**
     * Only the academic office (and roles holding the staff marker) may withdraw a
     * class from a KRS that has already been approved or locked.
     */
    protected function authorizeBatalTambah(StudentEnrollment $enrollment): void
    {
        $user = Auth::user();

        if ($user && $user->can(self::BATAL_TAMBAH_PERMISSION)) {
            return;
        }

        throw ValidationException::withMessages([
            'enrollment' => [
                "KRS sudah berstatus '{$enrollment->status->value}'. Pembatalan mata kuliah (batal-tambah) hanya dapat dilakukan oleh Bagian Akademik."
            ],
        ]);
    }
}
