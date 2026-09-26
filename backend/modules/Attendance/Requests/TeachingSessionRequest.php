<?php

namespace Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Enums\TeachingMethod;

class TeachingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        return $user->hasPermissionTo('attendance.manage')
            || $user->hasPermissionTo('attendance.record');
    }

    public function rules(): array
    {
        return [
            'academic_class_id' => ['required', 'integer', 'exists:academic_classes,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            // meeting_number adalah nomor pertemuan global per kelas; kelas dengan
            // dua jadwal per pekan bisa melewati 16.
            'meeting_number' => ['required', 'integer', 'min:1', 'max:60'],
            'session_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'teaching_method' => ['nullable', Rule::enum(TeachingMethod::class)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'status' => ['nullable', Rule::enum(SessionStatus::class)],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
