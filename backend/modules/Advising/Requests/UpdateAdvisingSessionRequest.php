<?php

namespace Modules\Advising\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advising\Enums\AdvisingSessionStatus;

class UpdateAdvisingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('advising.update_session') ?? false;
    }

    public function rules(): array
    {
        return [
            'session_date' => ['sometimes', 'required', 'date'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'required', 'string'],
            'status' => ['nullable', Rule::enum(AdvisingSessionStatus::class)],
        ];
    }
}
