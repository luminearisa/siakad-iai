<?php

namespace Modules\Academic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacultyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'code' => $this->code,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'study_programs' => StudyProgramResource::collection($this->whenLoaded('studyPrograms')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
