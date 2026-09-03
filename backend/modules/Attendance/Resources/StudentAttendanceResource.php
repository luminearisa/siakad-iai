<?php

namespace Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Student\Resources\StudentResource;

class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teaching_session_id' => $this->teaching_session_id,
            'student_id' => $this->student_id,
            'academic_class_id' => $this->academic_class_id,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'status_code' => $this->status?->code(),
            'notes' => $this->notes,
            'attachment_path' => $this->attachment_path,
            'recorded_by' => $this->recorded_by,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'student' => $this->whenLoaded('student', fn () => new StudentResource($this->student)),
            'teaching_session' => $this->whenLoaded('teachingSession'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
