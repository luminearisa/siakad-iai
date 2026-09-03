<?php

namespace Modules\Assessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_grade_id' => $this->student_grade_id,
            'old_score' => (float) $this->old_score,
            'new_score' => (float) $this->new_score,
            'reason' => $this->reason,
            'changed_by' => $this->changed_by,
            'modifier' => $this->whenLoaded('modifier', function () {
                return [
                    'id' => $this->modifier?->id,
                    'name' => $this->modifier?->name,
                    'email' => $this->modifier?->email,
                ];
            }),
            'changed_at' => $this->changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
