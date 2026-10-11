<?php

namespace App\Modules\RecipeMenu\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\RecipeMenu\Models\RecipeItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecipeService
{
    /**
     * List all recipes for current store context.
     */
    public function listRecipes(): Collection
    {
        return Recipe::with(['product', 'recipeItems.ingredient', 'recipeItems.unit'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get single recipe by ID.
     */
    public function getRecipe(string $id): Recipe
    {
        return Recipe::with(['product', 'recipeItems.ingredient', 'recipeItems.unit'])
            ->findOrFail($id);
    }

    /**
     * Create recipe with items, strictly enforcing cross-store integrity.
     */
    public function createRecipe(array $data): Recipe
    {
        $storeId = AppContext::storeId();

        // Cross-store validation: Product must belong to current store
        $product = Product::withoutGlobalScope('store')
            ->where('id', $data['product_id'])
            ->first();

        if (!$product || $product->store_id !== $storeId) {
            throw new InvalidArgumentException('The selected product does not belong to the current store.');
        }

        // Cross-store validation: All ingredients must belong to current store
        $ingredientIds = collect($data['items'])->pluck('ingredient_id')->unique()->all();
        $validIngredientsCount = Ingredient::withoutGlobalScope('store')
            ->whereIn('id', $ingredientIds)
            ->where('store_id', $storeId)
            ->count();

        if ($validIngredientsCount !== count($ingredientIds)) {
            throw new InvalidArgumentException('One or more selected ingredients do not belong to the current store.');
        }

        return DB::transaction(function () use ($data, $storeId) {
            $recipe = Recipe::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'status' => $data['status'] ?? CommonStatus::ACTIVE,
            ]);

            foreach ($data['items'] as $item) {
                RecipeItem::create([
                    'id' => (string) Str::ulid(),
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $recipe->load(['product', 'recipeItems.ingredient', 'recipeItems.unit']);
        });
    }

    /**
     * Update recipe and optionally replace recipe items.
     */
    public function updateRecipe(Recipe $recipe, array $data): Recipe
    {
        $storeId = AppContext::storeId();

        if (!empty($data['items'])) {
            $ingredientIds = collect($data['items'])->pluck('ingredient_id')->unique()->all();
            $validIngredientsCount = Ingredient::withoutGlobalScope('store')
                ->whereIn('id', $ingredientIds)
                ->where('store_id', $storeId)
                ->count();

            if ($validIngredientsCount !== count($ingredientIds)) {
                throw new InvalidArgumentException('One or more selected ingredients do not belong to the current store.');
            }
        }

        return DB::transaction(function () use ($recipe, $data) {
            $recipe->update(array_filter([
                'name' => $data['name'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($val) => !is_null($val)));

            if (!empty($data['items'])) {
                // Delete existing items
                $recipe->recipeItems()->delete();

                // Re-insert updated items
                foreach ($data['items'] as $item) {
                    RecipeItem::create([
                        'id' => (string) Str::ulid(),
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $item['ingredient_id'],
                        'unit_id' => $item['unit_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            return $recipe->fresh(['product', 'recipeItems.ingredient', 'recipeItems.unit']);
        });
    }

    /**
     * Delete recipe.
     */
    public function deleteRecipe(Recipe $recipe): void
    {
        $recipe->delete();
    }
}
