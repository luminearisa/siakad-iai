<?php

namespace Modules\Student\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentEducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'institution_name' => $this->institution_name,
            'level' => $this->level,
            'major' => $this->major,
            'graduation_year' => $this->graduation_year,
            'certificate_number' => $this->certificate_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
