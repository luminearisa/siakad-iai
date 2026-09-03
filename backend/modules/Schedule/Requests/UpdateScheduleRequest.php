<?php

namespace Modules\Schedule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Enums\ScheduleStatus;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('schedules.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'required', 'integer', 'exists:academic_classes,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['sometimes', 'required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::enum(ScheduleStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
