<?php

namespace Modules\Integrator\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Integrator\Models\ApiClient
 */
class ApiClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'contact_email' => $this->contact_email,
            'is_active' => (bool) $this->is_active,
            'allowed_ips' => $this->allowed_ips ?? [],
            'rate_limit_per_minute' => (int) $this->rate_limit_per_minute,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'keys_count' => $this->whenCounted('keys'),
            'active_keys_count' => $this->whenCounted('activeKeys'),
            'keys' => ApiKeyResource::collection($this->whenLoaded('keys')),
        ];
    }
}
