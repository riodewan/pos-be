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
            'current_stock' => $this->whenLoaded('inventoryStocks', fn () => (float) ($this->inventoryStocks->first()?->quantity ?? 0), 0),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'storage_location' => $this->storage_location,
            'shelf_life_value' => $this->shelf_life_value,
            'shelf_life_unit' => $this->shelf_life_unit,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'recipe_items' => $this->whenLoaded('recipeItems', fn () => $this->recipeItems->map(fn ($ri) => [
                'id' => $ri->id,
                'quantity' => (float) $ri->quantity,
                'product_name' => $ri->recipe?->product?->name ?? $ri->recipe?->name ?? 'Menu',
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
