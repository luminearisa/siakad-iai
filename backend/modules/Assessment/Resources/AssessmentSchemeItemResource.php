<?php

namespace Modules\Assessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSchemeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_scheme_id' => $this->assessment_scheme_id,
            'assessment_component_id' => $this->assessment_component_id,
            'weight' => (float) $this->weight,
            'component' => new AssessmentComponentResource($this->whenLoaded('component')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
