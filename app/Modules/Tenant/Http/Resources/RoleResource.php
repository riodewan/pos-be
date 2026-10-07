<?php

namespace App\Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'scope' => $this->scope,
            'is_default' => $this->is_default,
            'description' => $this->description,
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'module' => $p->module,
                        'description' => $p->description,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
