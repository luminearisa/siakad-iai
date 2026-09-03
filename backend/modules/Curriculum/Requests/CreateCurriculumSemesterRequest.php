<?php

namespace Modules\Curriculum\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCurriculumSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('curricula.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'semester_number' => ['required', 'integer', 'min:1', 'max:20'],
            'name' => ['nullable', 'string', 'max:50'],
            'recommended_credits' => ['nullable', 'integer', 'min:0', 'max:30'],
        ];
    }
}
