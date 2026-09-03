<?php

namespace Modules\Lecturer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Student\Enums\Gender;

class CreateLecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('lecturers.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'homebase_study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'lecturer_number' => ['nullable', 'string', 'max:50', 'unique:lecturers,lecturer_number'],
            'nidn' => ['nullable', 'string', 'max:50', 'unique:lecturers,nidn'],
            'nidk' => ['nullable', 'string', 'max:50', 'unique:lecturers,nidk'],
            'nip' => ['nullable', 'string', 'max:50', 'unique:lecturers,nip'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'academic_degree' => ['nullable', 'string', 'max:100'],
            'functional_position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(LecturerStatus::class)],
            'join_date' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'educations' => ['nullable', 'array'],
            'educations.*.degree' => ['required_with:educations', 'string', 'max:20'],
            'educations.*.institution_name' => ['required_with:educations', 'string', 'max:255'],
            'educations.*.major' => ['nullable', 'string', 'max:100'],
            'educations.*.graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'expertises' => ['nullable', 'array'],
            'expertises.*.name' => ['required_with:expertises', 'string', 'max:100'],
            'expertises.*.description' => ['nullable', 'string'],
        ];
    }
}
