<?php

namespace Modules\Academic\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academic\Enums\AcademicStatus;

class FacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('faculties.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('faculty')?->id ?? $this->route('faculty');

        return [
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('faculties', 'code')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }
}
