<?php

namespace Modules\Lecturer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Lecturer\Enums\LecturerStatus;

class ChangeLecturerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('lecturers.change_status') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LecturerStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
