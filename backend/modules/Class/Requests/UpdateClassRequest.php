<?php

namespace Modules\Class\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Class\Enums\ClassStatus;

class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('classes.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('class')?->id ?? $this->route('class');

        return [
            'semester_id' => ['sometimes', 'required', 'integer', 'exists:semesters,id'],
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'section' => ['sometimes', 'required', 'string', 'max:20'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', Rule::enum(ClassStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
