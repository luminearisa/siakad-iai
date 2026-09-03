<?php

namespace Modules\Assessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSchemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_class_id' => $this->academic_class_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => method_exists($this->status, 'label') ? $this->status->label() : (string) $this->status,
            'total_weight' => (float) $this->total_weight,
            'is_active' => (bool) $this->is_active,
            'items' => AssessmentSchemeItemResource::collection($this->whenLoaded('items')),
            'components_count' => $this->items ? $this->items->count() : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
