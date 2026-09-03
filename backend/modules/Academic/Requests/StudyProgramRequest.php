<?php

namespace Modules\Academic\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\DegreeLevel;

class StudyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('study_programs.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('study_program')?->id ?? $this->route('study_program');

        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('study_programs', 'code')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['required', Rule::enum(DegreeLevel::class)],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }
}
