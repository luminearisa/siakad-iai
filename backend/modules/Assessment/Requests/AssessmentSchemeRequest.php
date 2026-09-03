<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.assessment_component_id' => ['required_with:items', 'integer', 'exists:assessment_components,id'],
            'items.*.weight' => ['required_with:items', 'numeric', 'min:0.01', 'max:100.00'],
        ];
    }
}
