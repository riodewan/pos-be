<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Core\Enums\CommonStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeRecipe = null;
        $recipeItemsPayload = [];
        $availablePortions = null;
        $computedRecipeHpp = 0.0;

        if ($this->relationLoaded('recipes')) {
            $activeRecipe = $this->recipes->firstWhere('status', CommonStatus::ACTIVE)
                ?? $this->recipes->first();

            if ($activeRecipe && $activeRecipe->relationLoaded('recipeItems') && $activeRecipe->recipeItems->isNotEmpty()) {
                $minPortions = PHP_INT_MAX;

                foreach ($activeRecipe->recipeItems as $ri) {
                    $ing = $ri->relationLoaded('ingredient') ? $ri->ingredient : null;
                    $unit = ($ri->relationLoaded('unit') && $ri->unit)
                        ? $ri->unit
                        : ($ing && $ing->relationLoaded('unit') ? $ing->unit : null);

                    $qty = (float) $ri->quantity;
                    $unitCost = (float) ($ing?->cost_price ?? 0);
                    $subtotalCost = round($qty * $unitCost, 2);
                    $computedRecipeHpp += $subtotalCost;

                    $currentStock = 0.0;
                    if ($ing && $ing->relationLoaded('inventoryStocks')) {
                        $currentStock = (float) ($ing->inventoryStocks->firstWhere('store_id', $this->store_id)?->quantity
                            ?? $ing->inventoryStocks->first()?->quantity
                            ?? 0);
                    }

                    if ($qty > 0) {
                        $portionsForIng = (int) floor(max(0, $currentStock) / $qty);
                        $minPortions = min($minPortions, $portionsForIng);
                    }

                    $recipeItemsPayload[] = [
                        'id' => $ri->id,
                        'recipe_id' => $ri->recipe_id,
                        'ingredient_id' => $ri->ingredient_id,
                        'ingredient_name' => $ing?->name ?? 'Bahan',
                        'unit_id' => $ri->unit_id,
                        'unit_code' => $unit?->code ?? $unit?->name ?? 'pcs',
                        'unit_name' => $unit?->name ?? 'Pcs',
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'subtotal_cost' => $subtotalCost,
                        'current_stock' => $currentStock,
                    ];
                }

                if ($minPortions !== PHP_INT_MAX) {
                    $availablePortions = $minPortions;
                }
            }
        }

        $effectiveCostPrice = (float) ($this->cost_price ?? 0);
        if ($computedRecipeHpp > 0 && $effectiveCostPrice <= 0) {
            $effectiveCostPrice = round($computedRecipeHpp, 2);
        }

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'selling_price' => (float) $this->selling_price,
            'status' => $this->status,
            'category' => $this->category,
            'type' => $this->type,
            'cost_price' => $effectiveCostPrice,
            'available_portions' => $availablePortions,
            'recipe' => $activeRecipe ? [
                'id' => $activeRecipe->id,
                'name' => $activeRecipe->name,
                'status' => $activeRecipe->status,
                'items' => $recipeItemsPayload,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
