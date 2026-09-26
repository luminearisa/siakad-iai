<?php

namespace Modules\Enrollment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\SemesterResource;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Services\EnrollmentValidationService;
use Modules\Identity\Resources\UserResource;
use Modules\Student\Resources\StudentResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items, null);

        // Batal-tambah rows (dropped/cancelled) are history: they must never be
        // counted as study load nor offered as "still registered" in the UI.
        $activeItems = $items?->filter(
            fn ($item) => ($item->status instanceof EnrollmentItemStatus
                ? $item->status->value
                : (string) $item->status) === EnrollmentItemStatus::ENROLLED->value
        );

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'semester_id' => $this->semester_id,
            'semester' => new SemesterResource($this->whenLoaded('semester')),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'total_credits' => $this->total_credits,
            'max_credits' => $this->resolveMaxCredits(),
            'academic_advisor' => $this->student?->academicAdvisor?->lecturer?->full_name,
            'academic_advisor_id' => $this->student?->academicAdvisor?->lecturer_id,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'notes' => $this->notes,
            'items' => EnrollmentItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', fn () => $activeItems?->count() ?? $this->items->count()),
            'dropped_items_count' => $this->whenLoaded(
                'items',
                fn () => $this->items->count() - ($activeItems?->count() ?? $this->items->count())
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * The SKS ceiling that actually applies to this KRS: the stored quota when it
     * is set, otherwise the student's IPS tier resolved on the fly so the UI never
     * falls back to a hardcoded 24.
     */
    protected function resolveMaxCredits(): int
    {
        $stored = (int) ($this->max_credits ?? 0);

        if ($stored > 0) {
            return $stored;
        }

        $student = $this->student ?? $this->resource?->student;

        if (!$student) {
            return 24;
        }

        try {
            return app(EnrollmentValidationService::class)->getMaxCredits($student, $this->resource);
        } catch (\Throwable) {
            return 24;
        }
    }
}
