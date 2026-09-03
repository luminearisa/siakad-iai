<?php

namespace Modules\Advising\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Resources\EnrollmentResource;
use Modules\Lecturer\Resources\LecturerResource;
use Modules\Student\Resources\StudentResource;

class AdvisingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'lecturer_id' => $this->lecturer_id,
            'lecturer' => new LecturerResource($this->whenLoaded('lecturer')),
            'enrollment_id' => $this->enrollment_id,
            'enrollment' => new EnrollmentResource($this->whenLoaded('enrollment')),
            'session_date' => $this->session_date?->format('Y-m-d'),
            'topic' => $this->topic,
            'notes' => $this->notes,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
