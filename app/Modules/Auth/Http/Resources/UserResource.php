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
                if ($this->is_internal) {
                    return \App\Modules\Tenant\Models\Tenant::all()->map(fn ($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'status' => $t->status?->value ?? $t->status,
                        'role' => 'SUPER_ADMIN',
                    ]);
                }

                return $this->tenants->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'status' => $t->status?->value ?? $t->status,
                    'role' => $this->relationLoaded('userRoles') 
                        ? ($this->userRoles->firstWhere('tenant_id', $t->id)?->role?->name ?? 'STAFF')
                        : 'STAFF',
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

            'permissions' => $this->whenLoaded('userRoles', function () {
                if ($this->is_internal) {
                    return \App\Modules\Rbac\Models\Permission::pluck('name')->all();
                }

                $perms = [];
                foreach ($this->userRoles as $ur) {
                    if ($ur->role && $ur->role->relationLoaded('permissions')) {
                        foreach ($ur->role->permissions as $p) {
                            $perms[$p->name] = true;
                        }
                    }
                }
                return array_keys($perms);
            }),
        ];
    }
}
