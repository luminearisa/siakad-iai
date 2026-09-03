<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;

class RemoveEnrollmentItemAction
{
    public function execute(StudentEnrollment $enrollment, StudentEnrollmentItem $item): bool
    {
        if (!$enrollment->isEditable()) {
            throw ValidationException::withMessages([
                'enrollment' => ["Cannot modify KRS in status '{$enrollment->status->value}'."],
            ]);
        }

        if ($item->enrollment_id !== $enrollment->id) {
            throw ValidationException::withMessages([
                'item' => ['Item does not belong to this enrollment.'],
            ]);
        }

        return DB::transaction(function () use ($enrollment, $item) {
            $classId = $item->class_id;
            $oldValues = $item->toArray();

            // Lock class row
            $class = AcademicClass::where('id', $classId)->lockForUpdate()->first();

            $item->delete();

            if ($class && $class->enrolled_count > 0) {
                $class->decrement('enrolled_count');
            }

            $enrollment->recalculateCredits();

            AuditService::log(
                action: 'item_removed',
                module: 'Enrollment',
                description: "Class item #{$classId} removed from KRS #{$enrollment->id}.",
                entity: null,
                oldValues: $oldValues,
                newValues: null
            );

            return true;
        });
    }
}
