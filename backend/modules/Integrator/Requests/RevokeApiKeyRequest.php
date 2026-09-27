<?php

namespace Modules\Integrator\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function reason(): ?string
    {
        $value = $this->validated('reason');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
