<?php

namespace Modules\Student\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\StudentStatus;

class CreateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('students.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'national_student_number' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', Rule::enum(Gender::class)],
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
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'entry_date' => ['nullable', 'date'],
            'graduation_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'photo_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'families' => ['nullable', 'array'],
            'families.*.relationship' => ['required_with:families', 'string', 'in:father,mother,guardian'],
            'families.*.full_name' => ['required_with:families', 'string', 'max:255'],
            'families.*.phone' => ['nullable', 'string', 'max:30'],
            'families.*.occupation' => ['nullable', 'string', 'max:100'],
            'families.*.address' => ['nullable', 'string'],
            'educations' => ['nullable', 'array'],
            'educations.*.institution_name' => ['required_with:educations', 'string', 'max:255'],
            'educations.*.level' => ['required_with:educations', 'string', 'max:30'],
            'educations.*.major' => ['nullable', 'string', 'max:100'],
            'educations.*.graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
        ];
    }
}
