<?php

namespace Modules\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable'],
        ];
    }
}
