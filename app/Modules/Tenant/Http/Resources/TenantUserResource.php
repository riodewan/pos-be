<?php

namespace App\Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
                'job_title' => $this->user?->job_title,
                'avatar_url' => $this->user?->avatar_url,
                'is_active' => $this->user?->is_active,
            ],
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($ur) {
                    return [
                        'id' => $ur->id,
                        'role_id' => $ur->role_id,
                        'role_name' => $ur->role?->name,
                        'store_id' => $ur->store_id,
                        'store_name' => $ur->store?->name,
                    ];
                });
            }),
            'joined_at' => $this->joined_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
