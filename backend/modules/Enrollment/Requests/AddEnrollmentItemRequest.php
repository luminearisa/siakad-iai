<?php

namespace Modules\Enrollment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Enrollment\Models\StudentEnrollment;

class AddEnrollmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        $enrollment = $this->route('enrollment');
        $enrollmentModel = $enrollment instanceof StudentEnrollment
            ? $enrollment
            : StudentEnrollment::find($enrollment);

        if ($user->hasRole('mahasiswa')) {
            $student = $user->student;
            return $student && $enrollmentModel && $enrollmentModel->student_id === $student->id;
        }

        return $user->hasPermissionTo('enrollments.update');
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:academic_classes,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
