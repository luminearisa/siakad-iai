<?php

namespace Modules\Integrator\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Integrator\Models\ApiRequestLog
 */
class ApiRequestLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'path' => $this->path,
            'query' => $this->query,
            'status_code' => (int) $this->status_code,
            'is_successful' => $this->isSuccessful(),
            'duration_ms' => (int) $this->duration_ms,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'slug' => $this->client->slug,
            ] : null),
            'key' => $this->whenLoaded('key', fn () => $this->key ? [
                'id' => $this->key->id,
                'name' => $this->key->name,
                'key_prefix' => $this->key->key_prefix,
            ] : null),
        ];
    }
}
