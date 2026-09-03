<?php

namespace Modules\Assessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_class_id' => $this->academic_class_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type?->value ?? $this->type,
            'type_label' => method_exists($this->type, 'label') ? $this->type->label() : (string) $this->type,
            'max_score' => (float) $this->max_score,
            'is_required' => (bool) $this->is_required,
            'sequence' => (int) $this->sequence,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
