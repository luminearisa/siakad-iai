<?php

namespace Modules\Academic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'krs_start_date' => $this->krs_start_date?->format('Y-m-d'),
            'krs_end_date' => $this->krs_end_date?->format('Y-m-d'),
            'kprs_start_date' => $this->kprs_start_date?->format('Y-m-d'),
            'kprs_end_date' => $this->kprs_end_date?->format('Y-m-d'),
            'lecture_start_date' => $this->lecture_start_date?->format('Y-m-d'),
            'lecture_end_date' => $this->lecture_end_date?->format('Y-m-d'),
            'uts_start_date' => $this->uts_start_date?->format('Y-m-d'),
            'uts_end_date' => $this->uts_end_date?->format('Y-m-d'),
            'uas_start_date' => $this->uas_start_date?->format('Y-m-d'),
            'uas_end_date' => $this->uas_end_date?->format('Y-m-d'),
            'min_attendance_uts_percentage' => $this->min_attendance_uts_percentage !== null ? (float) $this->min_attendance_uts_percentage : 50.00,
            'min_attendance_uas_percentage' => $this->min_attendance_uas_percentage !== null ? (float) $this->min_attendance_uas_percentage : 80.00,
            'total_teaching_weeks' => $this->total_teaching_weeks ?? 16,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
