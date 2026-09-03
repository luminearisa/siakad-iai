<?php

namespace Modules\Course\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'description' => $this->description,
            'study_program_id' => $this->study_program_id,
            'study_program' => $this->whenLoaded('studyProgram'),
            'course_type_id' => $this->course_type_id,
            'course_type' => $this->whenLoaded('courseType'),
            'course_group_id' => $this->course_group_id,
            'course_group' => $this->whenLoaded('courseGroup'),
            'credits' => $this->credits,
            'theory_credits' => $this->theory_credits ?? 0,
            'practical_credits' => $this->practical_credits ?? 0,
            'field_practical_credits' => $this->field_practical_credits ?? 0,
            'simulation_credits' => $this->simulation_credits ?? 0,
            'seminar_credits' => $this->seminar_credits ?? 0,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'category' => $this->category,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'prerequisites' => CourseResource::collection($this->whenLoaded('prerequisites')),
            'dependents' => CourseResource::collection($this->whenLoaded('dependents')),
            'pivot' => $this->whenPivotLoaded('course_prerequisites', function () {
                return [
                    'minimum_grade' => $this->pivot->minimum_grade,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
