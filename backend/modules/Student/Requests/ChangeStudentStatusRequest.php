<?php

namespace Modules\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Student\Enums\StudentStatus;

class ChangeStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('students.change_status') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
