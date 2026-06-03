<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status ?? 'active',
            'roles' => method_exists($this->resource, 'getRoleNames') ? $this->getRoleNames()->values() : [],
            'permissions' => method_exists($this->resource, 'getAllPermissions')
                ? $this->getAllPermissions()->pluck('name')->values()
                : [],
            'last_login_at' => optional($this->last_login_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
