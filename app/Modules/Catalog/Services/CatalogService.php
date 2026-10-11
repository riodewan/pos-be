<?php

namespace App\Modules\Catalog\Services;

use App\Core\Enums\CommonStatus;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
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

    public function createUnit(array $data): Unit
    {
        return Unit::create([
            'id' => (string) Str::ulid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'],
        ]);
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
            'selling_price' => $data['selling_price'],
            'status' => $data['status'] ?? CommonStatus::ACTIVE,
        ]);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update(array_filter([
            'name' => $data['name'] ?? null,
            'sku' => $data['sku'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'selling_price' => $data['selling_price'] ?? null,
            'status' => $data['status'] ?? null,
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
        return Ingredient::with('unit')->orderBy('name')->get();
    }

    public function getIngredient(string $id): Ingredient
    {
        return Ingredient::with('unit')->findOrFail($id);
    }

    public function createIngredient(array $data): Ingredient
    {
        $ingredient = Ingredient::create([
            'unit_id' => $data['unit_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'status' => $data['status'] ?? CommonStatus::ACTIVE,
        ]);

        return $ingredient->load('unit');
    }

    public function updateIngredient(Ingredient $ingredient, array $data): Ingredient
    {
        $ingredient->update(array_filter([
            'unit_id' => $data['unit_id'] ?? null,
            'name' => $data['name'] ?? null,
            'sku' => $data['sku'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($val) => !is_null($val)));

        return $ingredient->fresh()->load('unit');
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
