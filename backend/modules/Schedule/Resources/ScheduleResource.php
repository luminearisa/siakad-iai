<?php

namespace Modules\Schedule\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Class\Resources\ClassResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'academic_class' => new ClassResource($this->whenLoaded('academicClass')),
            'room_id' => $this->room_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'day_of_week' => $this->day_of_week instanceof \BackedEnum ? $this->day_of_week->value : $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
