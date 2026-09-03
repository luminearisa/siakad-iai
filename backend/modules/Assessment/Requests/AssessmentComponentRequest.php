<?php

namespace Modules\Assessment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessment\Enums\ComponentType;

class AssessmentComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $component = $this->route('component') ?? $this->route('id');
        $componentId = is_object($component) ? $component->id : (int) $component;
        $classId = $this->input('academic_class_id') 
            ?? (is_object($component) ? $component->academic_class_id : null) 
            ?? $this->route('class')?->id;

        return [
            'academic_class_id' => [$this->isMethod('POST') && !$this->route('class') ? 'required' : 'sometimes', 'integer', 'exists:academic_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assessment_components', 'code')
                    ->where(function ($query) use ($classId) {
                        if ($classId) {
                            $query->where('academic_class_id', $classId);
                        }
                    })
                    ->ignore($componentId),
            ],
            'type' => ['required', Rule::enum(ComponentType::class)],
            'max_score' => ['required', 'numeric', 'min:1.00', 'max:1000.00'],
            'is_required' => ['nullable', 'boolean'],
            'sequence' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
