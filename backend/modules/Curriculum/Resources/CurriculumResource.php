<?php

namespace Modules\Curriculum\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\StudyProgramResource;

class CurriculumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'study_program_id' => $this->study_program_id,
            'study_program' => new StudyProgramResource($this->whenLoaded('studyProgram')),
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'start_year' => $this->start_year,
            'end_year' => $this->end_year,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'total_credits' => $this->total_credits,
            'semesters' => CurriculumSemesterResource::collection($this->whenLoaded('semesters')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
