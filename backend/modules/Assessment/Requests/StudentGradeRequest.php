<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'assessment_component_id' => ['required', 'integer', 'exists:assessment_components,id'],
            'score' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
