<?php

namespace Modules\Lecturer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Student\Enums\Gender;

class UpdateLecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('lecturers.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('lecturer')?->id ?? $this->route('lecturer');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'homebase_study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'lecturer_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('lecturers', 'lecturer_number')->ignore($id),
            ],
            'nidn' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('lecturers', 'nidn')->ignore($id),
            ],
            'nidk' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('lecturers', 'nidk')->ignore($id),
            ],
            'nip' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('lecturers', 'nip')->ignore($id),
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'academic_degree' => ['nullable', 'string', 'max:100'],
            'functional_position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'join_date' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
