<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Catalog\Http\Resources\IngredientResource;
use App\Modules\Catalog\Http\Resources\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient' => new IngredientResource($this->whenLoaded('ingredient')),
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
