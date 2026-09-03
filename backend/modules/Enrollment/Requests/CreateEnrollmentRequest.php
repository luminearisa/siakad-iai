<?php

namespace Modules\Enrollment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        // Mahasiswa creating their own enrollment or staff with enrollments.create
        if ($user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student) return false;
            // Force student_id in request to match authenticated student
            $this->merge(['student_id' => $student->id]);
            return true;
        }

        return $user->hasPermissionTo('enrollments.create');
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
