<?php

namespace Modules\Enrollment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('enrollments.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
