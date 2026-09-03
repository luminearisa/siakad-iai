<?php

namespace Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Lecturer\Resources\LecturerResource;

class TeachingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_class_id' => $this->academic_class_id,
            'schedule_id' => $this->schedule_id,
            'lecturer_id' => $this->lecturer_id,
            'meeting_number' => $this->meeting_number,
            'session_date' => $this->session_date ? $this->session_date->format('Y-m-d') : null,
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'topic' => $this->topic,
            'notes' => $this->notes,
            'teaching_method' => $this->teaching_method?->value ?? $this->teaching_method,
            'teaching_method_label' => $this->teaching_method?->label(),
            'room_id' => $this->room_id,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'check_in_code' => $this->check_in_code,
            'check_in_expires_at' => $this->check_in_expires_at?->toISOString(),
            'is_check_in_active' => $this->check_in_code && $this->check_in_expires_at && $this->check_in_expires_at->isFuture(),
            'academic_class' => $this->whenLoaded('academicClass'),
            'lecturer' => $this->whenLoaded('lecturer', fn () => new LecturerResource($this->lecturer)),
            'room' => $this->whenLoaded('room'),
            'attendances_count' => $this->whenCounted('attendances', $this->attendances_count),
            'present_count' => $this->when(isset($this->present_count), $this->present_count),
            'permit_count' => $this->when(isset($this->permit_count), $this->permit_count),
            'sick_count' => $this->when(isset($this->sick_count), $this->sick_count),
            'absent_count' => $this->when(isset($this->absent_count), $this->absent_count),
            'attendances' => StudentAttendanceResource::collection($this->whenLoaded('attendances')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
