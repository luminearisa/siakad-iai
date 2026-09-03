<?php

namespace Modules\Schedule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Enums\ScheduleStatus;

class CreateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('schedules.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:academic_classes,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::enum(ScheduleStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
