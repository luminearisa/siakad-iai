<?php

namespace Modules\Integrator\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes `key_hash`; the plaintext token is returned once by the controller
 * as a sibling field, not as part of this resource.
 *
 * @mixin \Modules\Integrator\Models\ApiKey
 */
class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'api_client_id' => $this->api_client_id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'scopes' => $this->scopeList(),
            'scope_details' => $this->scopeDetails(),
            'status' => $this->status()->value,
            'status_label' => $this->status()->label(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_reason' => $this->revoked_reason,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_used_ip' => $this->last_used_ip,
            'request_count' => (int) $this->request_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'slug' => $this->client->slug,
                'is_active' => (bool) $this->client->is_active,
            ] : null),
        ];
    }
}
