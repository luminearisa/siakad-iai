<?php

namespace Modules\Integrator\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiClientRequest extends FormRequest
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
        $clientId = $this->route('client')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:150', 'alpha_dash', Rule::unique('api_clients', 'slug')->ignore($clientId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', 'max:45', 'regex:/^[0-9a-fA-F:.\/]+$/'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowed_ips.*.regex' => 'Format IP tidak valid. Gunakan alamat IP atau CIDR, contoh 10.0.0.0/8.',
        ];
    }
}
