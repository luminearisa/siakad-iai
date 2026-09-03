<?php

namespace Modules\Schedule\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\InstitutionResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'building_id' => $this->building_id,
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'building_model' => $this->whenLoaded('buildingModel'),
            'code' => $this->code,
            'name' => $this->name,
            'building' => $this->buildingModel?->name ?? $this->building,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'room_type' => $this->room_type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
