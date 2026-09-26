<?php

namespace Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelfCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'teaching_session_id' => ['required', 'integer', 'exists:teaching_sessions,id'],
            'check_in_code' => ['required', 'string', 'min:4', 'max:10'],
        ];
    }
}
