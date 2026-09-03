<?php

namespace Modules\Enrollment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('enrollments.reject') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
