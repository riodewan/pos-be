<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'name' => $this->name,
            'sku' => $this->sku,
            'status' => $this->status,
            'category' => $this->category,
            'cost_price' => (float) ($this->cost_price ?? 0),
            'min_stock' => (float) ($this->min_stock ?? 0),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'supplier_id' => $this->supplier_id,
            'storage_location' => $this->storage_location,
            'shelf_life_value' => $this->shelf_life_value,
            'shelf_life_unit' => $this->shelf_life_unit,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
