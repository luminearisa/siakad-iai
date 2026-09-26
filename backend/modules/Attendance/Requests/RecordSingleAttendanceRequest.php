<?php

namespace Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Attendance\Enums\AttendanceStatus;

/**
 * Koreksi satu baris presensi. `status` dulu divalidasi tanpa aturan enum sama
 * sekali, sehingga nilai salah melempar ValueError (HTTP 500) alih-alih 422.
 */
class RecordSingleAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): callable
    {
        return function (Validator $validator) {
            $status = AttendanceStatus::tryFrom((string) $this->input('status'));

            if ($status && $status->requiresNote() && blank($this->input('notes'))) {
                $validator->errors()->add('notes', "Keterangan wajib diisi untuk status {$status->label()}.");
            }
        };
    }
}
