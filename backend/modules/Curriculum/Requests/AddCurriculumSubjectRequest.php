<?php

namespace Modules\Curriculum\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCurriculumSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('curricula.manage_subjects') ?? false;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'is_mandatory' => ['nullable', 'boolean'],
            'subject_type' => ['nullable', 'string', 'in:wajib,pilihan'],
            'is_package' => ['nullable', 'boolean'],
            'credits_override' => ['nullable', 'integer', 'min:1', 'max:20'],
            'minimum_grade' => ['nullable', 'string', 'max:5'],
            'prerequisites_text' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
