<?php

namespace App\Modules\RecipeMenu\Http\Resources;

use App\Modules\Catalog\Http\Resources\IngredientResource;
use App\Modules\Catalog\Http\Resources\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_id' => $this->recipe_id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient' => new IngredientResource($this->whenLoaded('ingredient')),
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'quantity' => (float) $this->quantity,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
