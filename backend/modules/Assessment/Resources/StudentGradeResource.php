<?php

namespace Modules\Assessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentGradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student?->id,
                    'student_number' => $this->student?->student_number,
                    'full_name' => $this->student?->full_name,
                    'study_program' => $this->student?->studyProgram?->name,
                ];
            }),
            'academic_class_id' => $this->academic_class_id,
            'academic_class' => $this->whenLoaded('academicClass', function () {
                return [
                    'id' => $this->academicClass?->id,
                    'code' => $this->academicClass?->code,
                    'name' => $this->academicClass?->name,
                    'section' => $this->academicClass?->section,
                ];
            }),
            'assessment_component_id' => $this->assessment_component_id,
            'component' => new AssessmentComponentResource($this->whenLoaded('component')),
            'score' => (float) $this->score,
            'graded_by' => $this->graded_by,
            'grader' => $this->whenLoaded('grader', function () {
                return [
                    'id' => $this->grader?->id,
                    'name' => $this->grader?->name,
                ];
            }),
            'graded_at' => $this->graded_at?->toIso8601String(),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => method_exists($this->status, 'label') ? $this->status->label() : (string) $this->status,
            'notes' => $this->notes,
            'revisions' => GradeRevisionResource::collection($this->whenLoaded('revisions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
