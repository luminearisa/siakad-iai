<?php

namespace Modules\Lecturer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LecturerEducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lecturer_id' => $this->lecturer_id,
            'degree' => $this->degree,
            'institution_name' => $this->institution_name,
            'major' => $this->major,
            'graduation_year' => $this->graduation_year,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
