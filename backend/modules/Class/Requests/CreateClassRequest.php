<?php

namespace Modules\Class\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Class\Enums\ClassStatus;

class CreateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('classes.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'section' => [
                'required',
                'string',
                'max:20',
                Rule::unique('academic_classes')
                    ->where('semester_id', $this->semester_id)
                    ->where('course_id', $this->course_id)
                    ->whereNull('deleted_at'),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', Rule::enum(ClassStatus::class)],
            'notes' => ['nullable', 'string'],
            'lecturers' => ['nullable', 'array'],
            'lecturers.*.lecturer_id' => ['required_with:lecturers', 'integer', 'exists:lecturers,id'],
            'lecturers.*.role' => ['nullable', 'string', 'in:primary,assistant,co_lecturer'],
        ];
    }
}
