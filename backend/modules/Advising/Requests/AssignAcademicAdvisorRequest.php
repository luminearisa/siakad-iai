<?php

namespace Modules\Advising\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignAcademicAdvisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('advising.assign') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
