<?php

namespace Modules\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Student\Enums\Gender;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('students.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('student')?->id ?? $this->route('student');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'study_program_id' => ['sometimes', 'required', 'integer', 'exists:study_programs,id'],
            'student_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_number')->ignore($id),
            ],
            'national_student_number' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'religion' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'entry_date' => ['nullable', 'date'],
            'graduation_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'photo_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
