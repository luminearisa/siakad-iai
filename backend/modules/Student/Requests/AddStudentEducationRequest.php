<?php

namespace Modules\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddStudentEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('students.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', 'max:30'],
            'major' => ['nullable', 'string', 'max:100'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
