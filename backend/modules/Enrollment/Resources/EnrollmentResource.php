<?php

namespace Modules\Enrollment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\SemesterResource;
use Modules\Identity\Resources\UserResource;
use Modules\Student\Resources\StudentResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'semester_id' => $this->semester_id,
            'semester' => new SemesterResource($this->whenLoaded('semester')),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'total_credits' => $this->total_credits,
            'max_credits' => $this->max_credits ?? 24,
            'academic_advisor' => $this->student?->academicAdvisor?->lecturer?->full_name,
            'academic_advisor_id' => $this->student?->academicAdvisor?->lecturer_id,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'notes' => $this->notes,
            'items' => EnrollmentItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
