<?php

namespace Modules\Class\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignClassLecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('classes.assign_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            'role' => ['nullable', 'string', 'in:primary,assistant,co_lecturer'],
        ];
    }
}
