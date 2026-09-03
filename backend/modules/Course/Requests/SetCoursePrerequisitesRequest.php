<?php

namespace Modules\Course\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCoursePrerequisitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('courses.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'prerequisites' => ['required', 'array'],
            'prerequisites.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'prerequisites.*.minimum_grade' => ['nullable', 'string', 'max:5'],
        ];
    }
}
