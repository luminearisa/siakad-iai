<?php

namespace Modules\Curriculum\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumSemesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'curriculum_id' => $this->curriculum_id,
            'semester_number' => $this->semester_number,
            'name' => $this->name,
            'recommended_credits' => $this->recommended_credits,
            'subjects' => CurriculumSubjectResource::collection($this->whenLoaded('subjects')),
            'total_credits' => $this->whenLoaded('subjects', function () {
                return $this->subjects->sum->credits;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
