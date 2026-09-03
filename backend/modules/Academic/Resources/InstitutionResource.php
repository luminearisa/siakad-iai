<?php

namespace Modules\Academic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'website' => $this->website,
            'logo_path' => $this->logo_path,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'faculties' => FacultyResource::collection($this->whenLoaded('faculties')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
