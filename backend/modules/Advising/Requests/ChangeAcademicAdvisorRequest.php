<?php

namespace Modules\Advising\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeAcademicAdvisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('advising.assign') ?? false;
    }

    public function rules(): array
    {
        return [
            'new_lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            'change_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
