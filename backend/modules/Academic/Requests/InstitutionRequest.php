<?php

namespace Modules\Academic\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academic\Enums\AcademicStatus;

class InstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('institutions.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('institution')?->id ?? $this->route('institution');

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('institutions', 'code')->ignore($id),
            ],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }
}
