<?php

namespace Modules\Enrollment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Class\Resources\ClassResource;
use Modules\Course\Resources\CourseResource;

class EnrollmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'class_id' => $this->class_id,
            'academic_class' => new ClassResource($this->whenLoaded('academicClass')),
            'course_id' => $this->course_id,
            'course' => new CourseResource($this->course ?? $this->academicClass?->course),
            'credits' => $this->credits,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'finalized_at' => $this->finalized_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
