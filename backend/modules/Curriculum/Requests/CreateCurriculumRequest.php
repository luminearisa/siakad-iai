<?php

namespace Modules\Curriculum\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Curriculum\Enums\CurriculumStatus;

class CreateCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('curricula.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
            'curriculum_year_id' => ['nullable', 'integer', 'exists:curriculum_years,id'],
            'credit_limit_id' => ['nullable', 'integer', 'exists:credit_limits,id'],
            'grade_scale_id' => ['nullable', 'integer', 'exists:grade_scales,id'],
            'code' => ['required', 'string', 'max:50', 'unique:curricula,code'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'start_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'status' => ['nullable', Rule::enum(CurriculumStatus::class)],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
        ];
    }
}
