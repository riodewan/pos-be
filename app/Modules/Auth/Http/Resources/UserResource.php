<?php

namespace App\Modules\Auth\Http\Resources;

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
            'is_internal' => $this->is_internal,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tenants' => $this->whenLoaded('tenants', function () {
                return $this->tenants->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'status' => $t->status?->value ?? $t->status,
                ]);
            }),
            'roles' => $this->whenLoaded('userRoles', function () {
                return $this->userRoles->map(fn ($ur) => [
                    'id' => $ur->id,
                    'role_id' => $ur->role_id,
                    'role_name' => $ur->role?->name,
                    'role_scope' => $ur->role?->scope?->value ?? $ur->role?->scope,
                    'tenant_id' => $ur->tenant_id,
                    'store_id' => $ur->store_id,
                ]);
            }),
        ];
    }
}
