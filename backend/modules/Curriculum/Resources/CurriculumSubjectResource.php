<?php

namespace Modules\Curriculum\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Course\Resources\CourseResource;

class CurriculumSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'curriculum_semester_id' => $this->curriculum_semester_id,
            'course_id' => $this->course_id,
            'course' => new CourseResource($this->whenLoaded('course')),
            'is_mandatory' => (bool) $this->is_mandatory,
            'subject_type' => $this->subject_type ?? ($this->is_mandatory ? 'wajib' : 'pilihan'),
            'is_package' => (bool) ($this->is_package ?? true),
            'credits_override' => $this->credits_override,
            'effective_credits' => $this->credits,
            'minimum_grade' => $this->minimum_grade ?? 'D',
            'prerequisites_text' => $this->prerequisites_text,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
