<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentSchemeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assessment_component_id' => ['required', 'integer', 'exists:assessment_components,id'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100.00'],
        ];
    }
}
