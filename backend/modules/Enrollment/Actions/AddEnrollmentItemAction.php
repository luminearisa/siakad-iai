<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Models\EnrollmentStatusHistory;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Enrollment\Services\EnrollmentValidationService;

class AddEnrollmentItemAction
{
    public function __construct(
        protected EnrollmentValidationService $validator
    ) {}

    public function execute(StudentEnrollment $enrollment, int $classId, ?string $notes = null, bool $bypassCurriculum = false): StudentEnrollmentItem
    {
        if (!$enrollment->isEditable()) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot modify KRS in status '{$enrollment->status->value}'. Only draft or revision_required enrollments can be modified."],
            ]);
        }

        return DB::transaction(function () use ($enrollment, $classId, $notes, $bypassCurriculum) {
            // Lock class row + eager-load relations needed by validator
            $class = AcademicClass::with(['course', 'studyProgram'])
                ->where('id', $classId)
                ->lockForUpdate()
                ->firstOrFail();

            // Run comprehensive validation rules
            $this->validator->validateClassAddition($enrollment, $class, $bypassCurriculum);

            $credits = (int) ($class->course?->credits ?? 2);

            // A class that was previously cancelled (draft) or dropped (batal-tambah)
            // keeps its row as an audit trail, and (enrollment_id, class_id) is unique,
            // so re-taking it reactivates the existing row instead of inserting a new one.
            $item = $enrollment->items()
                ->where('class_id', $class->id)
                ->whereIn('status', [EnrollmentItemStatus::DROPPED->value, EnrollmentItemStatus::CANCELLED->value])
                ->first();

            if ($item) {
                $previousStatus = $item->status instanceof EnrollmentItemStatus ? $item->status->value : (string) $item->status;
                $item->update([
                    'course_id' => $class->course_id,
                    'credits' => $credits,
                    'status' => EnrollmentItemStatus::ENROLLED,
                    'notes' => $notes ?? $item->notes,
                ]);
            } else {
                $previousStatus = null;
                $item = $enrollment->items()->create([
                    'class_id' => $class->id,
                    'course_id' => $class->course_id,
                    'credits' => $credits,
                    'status' => EnrollmentItemStatus::ENROLLED,
                    'notes' => $notes,
                ]);
            }

            // Increment enrolled_count atomically
            $class->increment('enrolled_count');

            // Recalculate total credits on the enrollment header
            $enrollment->recalculateCredits();

            if ($previousStatus !== null) {
                EnrollmentStatusHistory::record(
                    enrollment: $enrollment,
                    action: 'item_reactivated',
                    fromStatus: $previousStatus,
                    toStatus: EnrollmentItemStatus::ENROLLED->value,
                    notes: 'Mata kuliah diambil kembali setelah pembatalan (batal-tambah).',
                    item: $item,
                    meta: ['class_id' => $class->id, 'course_id' => $class->course_id, 'credits' => $credits],
                );
            }

            AuditService::log(
                action: $previousStatus !== null ? 'item_reactivated' : 'item_added',
                module: 'Enrollment',
                description: "Class {$class->code} ({$credits} SKS) added to KRS #{$enrollment->id}.",
                entity: $item,
                oldValues: $previousStatus !== null ? ['status' => $previousStatus] : null,
                newValues: $item->toArray()
            );

            return $item->load(['academicClass.course', 'academicClass.lecturers', 'academicClass.schedules.room']);
        });
    }
}
