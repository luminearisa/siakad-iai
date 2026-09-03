<?php

namespace Modules\Class\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\SemesterResource;
use Modules\Academic\Resources\StudyProgramResource;
use Modules\Course\Resources\CourseResource;
use Modules\Lecturer\Resources\LecturerResource;

class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'semester_id' => $this->semester_id,
            'semester' => new SemesterResource($this->whenLoaded('semester')),
            'course_id' => $this->course_id,
            'course' => new CourseResource($this->whenLoaded('course')),
            'study_program_id' => $this->study_program_id,
            'study_program' => new StudyProgramResource($this->whenLoaded('studyProgram')),
            'code' => $this->code,
            'name' => $this->name,
            'section' => $this->section,
            'capacity' => $this->capacity,
            'enrolled_count' => $this->enrolled_count,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'lecturers' => LecturerResource::collection($this->whenLoaded('lecturers')),
            'schedules' => $this->whenLoaded('schedules', function () {
                return \Modules\Schedule\Resources\ScheduleResource::collection($this->schedules);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
