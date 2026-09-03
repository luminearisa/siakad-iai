<?php

namespace Modules\Student\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentFamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'relationship' => $this->relationship,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'occupation' => $this->occupation,
            'address' => $this->address,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
