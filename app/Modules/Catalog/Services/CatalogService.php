<?php

namespace App\Modules\Catalog\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\UnitType;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\System\Services\SettingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CatalogService
{
    // ==========================================
    // UNITS
    // ==========================================

    public function listUnits(): Collection
    {
        return Unit::orderBy('name')->get();
    }

    public function getUnit(string $id): Unit
    {
        return Unit::findOrFail($id);
    }

    public function createUnit(array $data): Unit
    {
        return Unit::create([
            'id' => (string) Str::ulid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'],
        ]);
    }

    public function updateUnit(Unit $unit, array $data): Unit
    {
        $unit->update(array_filter([
            'name' => $data['name'] ?? null,
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? null,
        ], fn ($val) => !is_null($val)));

        return $unit->fresh();
    }

    public function deleteUnit(Unit $unit): void
    {
        $unit->delete();
    }

    // ==========================================
    // INGREDIENT CATEGORIES (STORE SETTINGS + DB)
    // ==========================================

    public function listIngredientCategories(): array
    {
        $settingService = app(SettingService::class);
        $raw = $settingService->get('ingredient_categories');
        $saved = $raw ? (json_decode($raw, true) ?: []) : [];

        // Count ingredients per category in current store
        $counts = Ingredient::selectRaw('category, count(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $knownNames = [];
        $result = [];

        foreach ($saved as $item) {
            $name = $item['name'] ?? '';
            if (!$name) {
                continue;
            }
            $knownNames[strtolower($name)] = true;
            $result[] = [
                'id' => $item['id'] ?? ('icat-' . Str::slug($name)),
                'name' => $name,
                'code' => $item['code'] ?? ('CAT-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3))),
                'itemCount' => (int) ($counts[$name] ?? 0),
                'status' => $item['status'] ?? 'Aktif',
                'description' => $item['description'] ?? '',
            ];
        }

        // Also include any category present on existing ingredients in DB not yet in saved settings
        foreach ($counts as $catName => $total) {
            if ($catName && !isset($knownNames[strtolower($catName)])) {
                $result[] = [
                    'id' => 'icat-' . Str::slug($catName),
                    'name' => $catName,
                    'code' => 'CAT-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $catName), 0, 3)),
                    'itemCount' => (int) $total,
                    'status' => 'Aktif',
                    'description' => '',
                ];
            }
        }

        return $result;
    }

    public function saveIngredientCategory(array $data, ?string $id = null): array
    {
        $settingService = app(SettingService::class);
        $current = $this->listIngredientCategories();

        $name = trim($data['name']);
        $code = trim($data['code'] ?? '') ?: ('CAT-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)));
        $status = $data['status'] ?? 'Aktif';
        $description = $data['description'] ?? '';

        $found = false;
        $savedItem = null;

        if ($id) {
            foreach ($current as &$item) {
                if ($item['id'] === $id) {
                    $oldName = $item['name'];
                    $item['name'] = $name;
                    $item['code'] = $code;
                    $item['status'] = $status;
                    $item['description'] = $description;
                    $found = true;
                    $savedItem = $item;

                    if ($oldName !== $name) {
                        Ingredient::where('category', $oldName)->update(['category' => $name]);
                    }
                    break;
                }
            }
            unset($item);
        }

        if (!$found) {
            $savedItem = [
                'id' => $id ?: ('icat-' . strtolower((string) Str::ulid())),
                'name' => $name,
                'code' => $code,
                'itemCount' => (int) Ingredient::where('category', $name)->count(),
                'status' => $status,
                'description' => $description,
            ];
            $current[] = $savedItem;
        }

        $settingService->set('ingredient_categories', json_encode(array_values($current)));

        return $savedItem;
    }

    public function deleteIngredientCategory(string $id): void
    {
        $settingService = app(SettingService::class);
        $current = $this->listIngredientCategories();
        $filtered = array_values(array_filter($current, fn ($item) => ($item['id'] ?? '') !== $id));
        $settingService->set('ingredient_categories', json_encode($filtered));
    }

    // ==========================================
    // PRODUCTS
    // ==========================================

    public function listProducts(): Collection
    {
        return Product::orderBy('name')->get();
    }

    public function getProduct(string $id): Product
    {
        return Product::findOrFail($id);
    }

    public function createProduct(array $data): Product
    {
        return Product::create([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'selling_price' => $data['selling_price'],
            'status' => $data['status'] ?? CommonStatus::ACTIVE,
            'category' => $data['category'] ?? 'Kopi',
            'type' => $data['type'] ?? 'Minuman',
            'cost_price' => $data['cost_price'] ?? 0,
        ]);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update(array_filter([
            'name' => $data['name'] ?? null,
            'sku' => $data['sku'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'image_url' => array_key_exists('image_url', $data) ? $data['image_url'] : null,
            'selling_price' => $data['selling_price'] ?? null,
            'status' => $data['status'] ?? null,
            'category' => $data['category'] ?? null,
            'type' => $data['type'] ?? null,
            'cost_price' => array_key_exists('cost_price', $data) ? $data['cost_price'] : null,
        ], fn ($val) => !is_null($val)));

        return $product->fresh();
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }

    // ==========================================
    // INGREDIENTS
    // ==========================================

    public function listIngredients(): Collection
    {
        return Ingredient::with(['unit', 'supplier', 'inventoryStocks', 'recipeItems.recipe.product'])->orderBy('name')->get();
    }

    public function getIngredient(string $id): Ingredient
    {
        return Ingredient::with(['unit', 'supplier', 'inventoryStocks', 'recipeItems.recipe.product'])->findOrFail($id);
    }

    protected function resolveUnitId(array $data): ?string
    {
        if (!empty($data['unit_id'])) {
            return $data['unit_id'];
        }
        if (!empty($data['unit']) && is_string($data['unit'])) {
            $u = Unit::whereRaw('LOWER(code) = ?', [strtolower($data['unit'])])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($data['unit'])])
                ->first();
            if ($u) {
                return $u->id;
            }
            $newUnit = Unit::create([
                'id' => (string) Str::ulid(),
                'name' => ucfirst($data['unit']),
                'code' => strtolower($data['unit']),
                'type' => UnitType::WEIGHT,
            ]);
            return $newUnit->id;
        }
        $first = Unit::first();
        if ($first) {
            return $first->id;
        }
        $defaultUnit = Unit::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kilogram',
            'code' => 'kg',
            'type' => UnitType::WEIGHT,
        ]);
        return $defaultUnit->id;
    }

    protected function resolveSupplierId(array $data): ?string
    {
        if (array_key_exists('supplier_id', $data)) {
            return $data['supplier_id'];
        }
        if (!empty($data['supplier_name']) && is_string($data['supplier_name'])) {
            $sup = Supplier::where('name', $data['supplier_name'])->first();
            if ($sup) {
                return $sup->id;
            }
        }
        return null;
    }

    public function createIngredient(array $data): Ingredient
    {
        $unitId = $this->resolveUnitId($data);
        $supplierId = $this->resolveSupplierId($data);

        $ingredient = Ingredient::create([
            'unit_id' => $unitId,
            'name' => $data['name'],
            'sku' => $data['sku'],
            'status' => $data['status'] ?? CommonStatus::ACTIVE,
            'category' => $data['category'] ?? null,
            'cost_price' => $data['cost_price'] ?? 0,
            'min_stock' => $data['min_stock'] ?? 0,
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'supplier_id' => $supplierId,
            'storage_location' => $data['storage_location'] ?? null,
            'shelf_life_value' => $data['shelf_life_value'] ?? null,
            'shelf_life_unit' => $data['shelf_life_unit'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
        ]);

        $initialStock = isset($data['current_stock']) ? (float) $data['current_stock'] : 0;
        InventoryStock::updateOrCreate(
            [
                'store_id' => $ingredient->store_id,
                'ingredient_id' => $ingredient->id,
            ],
            [
                'id' => (string) Str::ulid(),
                'quantity' => max(0, $initialStock),
                'average_cost' => (float) ($data['cost_price'] ?? 0),
            ]
        );

        return $ingredient->load(['unit', 'supplier', 'inventoryStocks', 'recipeItems.recipe.product']);
    }

    public function updateIngredient(Ingredient $ingredient, array $data): Ingredient
    {
        $unitId = (!empty($data['unit_id']) || !empty($data['unit'])) ? $this->resolveUnitId($data) : null;
        $supplierId = $this->resolveSupplierId($data);

        $updatePayload = array_filter([
            'unit_id' => $unitId,
            'name' => $data['name'] ?? null,
            'sku' => $data['sku'] ?? null,
            'status' => $data['status'] ?? null,
            'category' => array_key_exists('category', $data) ? $data['category'] : null,
            'cost_price' => array_key_exists('cost_price', $data) ? $data['cost_price'] : null,
            'min_stock' => array_key_exists('min_stock', $data) ? $data['min_stock'] : null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'image_url' => array_key_exists('image_url', $data) ? $data['image_url'] : null,
            'storage_location' => array_key_exists('storage_location', $data) ? $data['storage_location'] : null,
            'shelf_life_value' => array_key_exists('shelf_life_value', $data) ? $data['shelf_life_value'] : null,
            'shelf_life_unit' => array_key_exists('shelf_life_unit', $data) ? $data['shelf_life_unit'] : null,
            'expiry_date' => array_key_exists('expiry_date', $data) ? $data['expiry_date'] : null,
        ], fn ($val) => !is_null($val));

        if (array_key_exists('supplier_id', $data) || array_key_exists('supplier_name', $data)) {
            $updatePayload['supplier_id'] = $supplierId;
        }

        $ingredient->update($updatePayload);

        if (array_key_exists('current_stock', $data) && $data['current_stock'] !== null) {
            $stock = InventoryStock::where('store_id', $ingredient->store_id)
                ->where('ingredient_id', $ingredient->id)
                ->first();
            if ($stock) {
                $stock->update([
                    'quantity' => max(0, (float) $data['current_stock']),
                    'average_cost' => isset($data['cost_price']) ? (float) $data['cost_price'] : $stock->average_cost,
                ]);
            } else {
                InventoryStock::create([
                    'id' => (string) Str::ulid(),
                    'store_id' => $ingredient->store_id,
                    'ingredient_id' => $ingredient->id,
                    'quantity' => max(0, (float) $data['current_stock']),
                    'average_cost' => (float) ($data['cost_price'] ?? $ingredient->cost_price ?? 0),
                ]);
            }
        }

        return $ingredient->fresh()->load(['unit', 'supplier', 'inventoryStocks', 'recipeItems.recipe.product']);
    }

    public function deleteIngredient(Ingredient $ingredient): void
    {
        $ingredient->delete();
    }

    // ==========================================
    // SUPPLIERS
    // ==========================================

    public function listSuppliers(): Collection
    {
        return Supplier::orderBy('name')->get();
    }

    public function getSupplier(string $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function createSupplier(array $data): Supplier
    {
        return Supplier::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? CommonStatus::ACTIVE,
        ]);
    }

    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        $supplier->update(array_filter([
            'name' => $data['name'] ?? null,
            'code' => $data['code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($val) => !is_null($val)));

        return $supplier->fresh();
    }

    public function deleteSupplier(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
