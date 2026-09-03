<?php

namespace Modules\Class\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Lecturer\Resources\LecturerResource;

class ClassLecturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'lecturer_id' => $this->lecturer_id,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'lecturer' => new LecturerResource($this->whenLoaded('lecturer')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
