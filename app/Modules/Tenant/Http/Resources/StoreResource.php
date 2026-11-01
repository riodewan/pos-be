<?php

namespace App\Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'maps_url' => $this->maps_url,
            'logo_url' => $this->logo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'category' => $this->category,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'operational_status' => $this->operational_status,
            'publication_status' => $this->publication_status,
            'operating_hours' => $this->operating_hours,
            'receipt_config' => $this->receipt_config,
            'facilities' => $this->facilities,
            'photos' => $this->photos,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
