<?php

namespace Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Enums\AttendanceStatus;

class BatchAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendances.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendances.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
