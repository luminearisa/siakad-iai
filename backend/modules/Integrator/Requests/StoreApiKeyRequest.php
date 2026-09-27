<?php

namespace Modules\Integrator\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Integrator\Enums\ApiKeyScope;

class StoreApiKeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiKeyScope::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scopes.required' => 'Pilih minimal satu scope untuk API key ini.',
            'scopes.*.in' => 'Scope tidak dikenal.',
            'expires_at.after' => 'Tanggal kedaluwarsa harus di masa depan.',
        ];
    }

    /**
     * Helper used by controllers/tests: validated scopes only.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        /** @var array<int, string> $scopes */
        $scopes = $this->validated('scopes');

        return array_values(array_unique($scopes));
    }

    /**
     * Expose the validated expiry as a string or null.
     */
    public function expiresAt(): ?string
    {
        $value = $this->validated('expires_at');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
