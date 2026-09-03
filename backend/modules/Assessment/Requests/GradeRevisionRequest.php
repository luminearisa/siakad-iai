<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradeRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_score' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
