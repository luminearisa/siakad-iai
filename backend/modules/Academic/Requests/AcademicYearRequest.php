<?php

namespace Modules\Academic\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academic\Enums\AcademicStatus;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('academic_years.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('academic_year')?->id ?? $this->route('academic_year');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_years', 'name')->ignore($id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }
}
