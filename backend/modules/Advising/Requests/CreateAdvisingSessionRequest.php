<?php

namespace Modules\Advising\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advising\Enums\AdvisingSessionStatus;

class CreateAdvisingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('advising.create_session') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            'enrollment_id' => ['nullable', 'integer', 'exists:student_enrollments,id'],
            'session_date' => ['required', 'date'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(AdvisingSessionStatus::class)],
        ];
    }
}
