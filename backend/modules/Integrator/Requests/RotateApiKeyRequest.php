<?php

namespace Modules\Integrator\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RotateApiKeyRequest extends FormRequest
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
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function expiresAt(): ?string
    {
        $value = $this->validated('expires_at');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function reason(): ?string
    {
        $value = $this->validated('reason');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
