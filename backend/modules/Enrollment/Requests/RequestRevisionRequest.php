<?php

namespace Modules\Enrollment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('enrollments.revise') ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:500'],
        ];
    }
}
