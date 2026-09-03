<?php

namespace Modules\Academic\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\SemesterType;

class SemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('semesters.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('semester')?->id ?? $this->route('semester');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(SemesterType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'krs_start_date' => ['nullable', 'date'],
            'krs_end_date' => ['nullable', 'date', 'after_or_equal:krs_start_date'],
            'kprs_start_date' => ['nullable', 'date'],
            'kprs_end_date' => ['nullable', 'date', 'after_or_equal:kprs_start_date'],
            'lecture_start_date' => ['nullable', 'date'],
            'lecture_end_date' => ['nullable', 'date'],
            'uts_start_date' => ['nullable', 'date'],
            'uts_end_date' => ['nullable', 'date'],
            'uas_start_date' => ['nullable', 'date'],
            'uas_end_date' => ['nullable', 'date'],
            'min_attendance_uts_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_attendance_uas_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_teaching_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }
}
