<?php

namespace Modules\Academic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'faculty_id' => $this->faculty_id,
            'code' => $this->code,
            'short_name' => $this->short_name,
            'name' => $this->name,
            'degree' => $this->degree instanceof \BackedEnum ? $this->degree->value : $this->degree,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'faculty' => new FacultyResource($this->whenLoaded('faculty')),
            'setting' => $this->setting,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
