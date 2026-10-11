<?php

namespace App\Modules\RecipeMenu\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\RecipeMenu\Models\Menu;
use App\Modules\RecipeMenu\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuService
{
    /**
     * List all menus for current store context.
     */
    public function listMenus(): Collection
    {
        return Menu::with('products')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get single menu by ID.
     */
    public function getMenu(string $id): Menu
    {
        return Menu::with('products')->findOrFail($id);
    }

    /**
     * Create menu and optionally attach products, enforcing cross-store validation.
     */
    public function createMenu(array $data): Menu
    {
        $storeId = AppContext::storeId();

        if (!empty($data['product_ids'])) {
            $productIds = array_unique($data['product_ids']);
            $validProductsCount = Product::withoutGlobalScope('store')
                ->whereIn('id', $productIds)
                ->where('store_id', $storeId)
                ->count();

            if ($validProductsCount !== count($productIds)) {
                throw new InvalidArgumentException('One or more selected products do not belong to the current store.');
            }
        }

        return DB::transaction(function () use ($data, $storeId) {
            $menu = Menu::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? CommonStatus::ACTIVE,
            ]);

            if (!empty($data['product_ids'])) {
                foreach ($data['product_ids'] as $index => $productId) {
                    MenuItem::create([
                        'id' => (string) Str::ulid(),
                        'menu_id' => $menu->id,
                        'product_id' => $productId,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $menu->load('products');
        });
    }

    /**
     * Update menu and optionally sync products.
     */
    public function updateMenu(Menu $menu, array $data): Menu
    {
        $storeId = AppContext::storeId();

        if (isset($data['product_ids'])) {
            $productIds = array_unique($data['product_ids']);
            $validProductsCount = Product::withoutGlobalScope('store')
                ->whereIn('id', $productIds)
                ->where('store_id', $storeId)
                ->count();

            if ($validProductsCount !== count($productIds)) {
                throw new InvalidArgumentException('One or more selected products do not belong to the current store.');
            }
        }

        return DB::transaction(function () use ($menu, $data) {
            $menu->update(array_filter([
                'name' => $data['name'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'status' => $data['status'] ?? null,
            ], fn ($val) => !is_null($val)));

            if (isset($data['product_ids'])) {
                $menu->menuItems()->delete();

                foreach ($data['product_ids'] as $index => $productId) {
                    MenuItem::create([
                        'id' => (string) Str::ulid(),
                        'menu_id' => $menu->id,
                        'product_id' => $productId,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $menu->fresh('products');
        });
    }

    /**
     * Delete menu.
     */
    public function deleteMenu(Menu $menu): void
    {
        $menu->delete();
    }
}
