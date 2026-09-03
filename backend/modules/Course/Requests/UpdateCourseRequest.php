<?php

namespace Modules\Course\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Course\Enums\CourseStatus;
use Modules\Course\Enums\CourseType;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('courses.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('course')?->id ?? $this->route('course');

        return [
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'course_type_id' => ['nullable', 'integer', 'exists:course_types,id'],
            'course_group_id' => ['nullable', 'integer', 'exists:course_groups,id'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('courses', 'code')->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'theory_credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'practical_credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'field_practical_credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'simulation_credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'seminar_credits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'type' => ['nullable', Rule::enum(CourseType::class)],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(CourseStatus::class)],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*.course_id' => ['required_with:prerequisites', 'integer', 'exists:courses,id'],
            'prerequisites.*.minimum_grade' => ['nullable', 'string', 'max:5'],
        ];
    }
}
