<?php

namespace Modules\Identity\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_names' => $this->roles->pluck('name')->values(),
            'permissions' => $this->allPermissions()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'display_name' => $p->display_name,
                'group' => $p->group,
            ])->values(),
            'permission_names' => $this->allPermissions()->pluck('name')->values(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
