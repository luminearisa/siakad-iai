<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.assessment_component_id' => ['required', 'integer', 'exists:assessment_components,id'],
            'grades.*.score' => ['required', 'numeric', 'min:0'],
            'grades.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
