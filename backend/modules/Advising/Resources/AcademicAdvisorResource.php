<?php

namespace Modules\Advising\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Lecturer\Resources\LecturerResource;
use Modules\Student\Resources\StudentResource;

class AcademicAdvisorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'lecturer_id' => $this->lecturer_id,
            'lecturer' => new LecturerResource($this->whenLoaded('lecturer')),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
