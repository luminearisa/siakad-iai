<?php

namespace Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Attendance\Enums\AttendanceStatus;

class BatchAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendances.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendances.*.notes' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Izin/Sakit/Alpa harus punya dasar tertulis; tanpa aturan ini satu klik
     * salvo "hadir" tetap lolos dan tidak bisa dibuktikan saat audit akademik.
     */
    public function after(): callable
    {
        return function (Validator $validator) {
            foreach ((array) $this->input('attendances', []) as $index => $row) {
                $status = AttendanceStatus::tryFrom((string) ($row['status'] ?? ''));

                if ($status && $status->requiresNote() && blank($row['notes'] ?? null)) {
                    $validator->errors()->add(
                        "attendances.{$index}.notes",
                        "Keterangan wajib diisi untuk status {$status->label()}."
                    );
                }
            }
        };
    }
}
