<?php

namespace Modules\Enrollment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Class\Resources\ClassResource;

/**
 * A class in the KRS catalog, annotated with whether the current student is
 * actually allowed to take it.
 *
 * @property array<string, list<string>> $eligibilityErrors
 */
class AvailableClassResource extends JsonResource
{
    /** @var array<string, list<string>> */
    protected array $eligibilityErrors = [];

    /**
     * Attach the validation errors collected for this class.
     *
     * @param  array<string, list<string>>  $errors
     */
    public function withEligibility(array $errors): static
    {
        $this->eligibilityErrors = $errors;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $reasons = collect($this->eligibilityErrors)->flatten()->values()->all();

        return array_merge((new ClassResource($this->resource))->toArray($request), [
            'is_eligible' => empty($reasons),
            'eligibility_reasons' => $reasons,
            'eligibility_reason' => $reasons[0] ?? null,
        ]);
    }
}
