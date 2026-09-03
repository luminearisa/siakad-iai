<?php

namespace Modules\Schedule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Schedule\Enums\RoomStatus;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('rooms.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('room')?->id ?? $this->route('room');

        return [
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('rooms', 'code')->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'room_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
        ];
    }
}
