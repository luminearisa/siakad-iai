<?php

namespace Modules\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddStudentFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('students.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'relationship' => ['required', 'string', 'in:father,mother,guardian'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
