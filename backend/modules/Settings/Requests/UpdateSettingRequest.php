<?php

namespace Modules\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'value' => ['required'],
            'type' => ['nullable', 'string', 'in:string,integer,boolean,json,float'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
