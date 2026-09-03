<?php

namespace Modules\Curriculum\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('curricula.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('curriculum')?->id ?? $this->route('curriculum');

        return [
            'study_program_id' => ['sometimes', 'required', 'integer', 'exists:study_programs,id'],
            'curriculum_year_id' => ['nullable', 'integer', 'exists:curriculum_years,id'],
            'credit_limit_id' => ['nullable', 'integer', 'exists:credit_limits,id'],
            'grade_scale_id' => ['nullable', 'integer', 'exists:grade_scales,id'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('curricula', 'code')->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'start_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
        ];
    }
}
