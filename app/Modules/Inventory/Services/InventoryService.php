<?php

namespace App\Modules\Inventory\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\MovementType;
use App\Core\Enums\PurchaseStatus;
use App\Core\Enums\WastageStatus;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\PurchaseItem;
use App\Modules\Inventory\Models\Wastage;
use App\Modules\Inventory\Models\WastageItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryService
{
    // ==========================================
    // PURCHASES
    // ==========================================

    public function listPurchases(): Collection
    {
        return Purchase::with(['supplier', 'purchaseItems.ingredient.unit', 'purchaseItems.unit'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPurchase(string $id): Purchase
    {
        return Purchase::with(['supplier', 'purchaseItems.ingredient.unit', 'purchaseItems.unit'])
            ->findOrFail($id);
    }

    public function createPurchase(array $data, ?string $userId = null): Purchase
    {
        $storeId = AppContext::storeId();

        // Cross-store validation: Supplier (if provided) must belong to current store
        if (!empty($data['supplier_id'])) {
            $supplier = Supplier::withoutGlobalScope('store')
                ->where('id', $data['supplier_id'])
                ->first();

            if (!$supplier || $supplier->store_id !== $storeId) {
                throw new InvalidArgumentException('The selected supplier does not belong to the current store.');
            }
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

        return DB::transaction(function () use ($data, $storeId, $userId) {
            $purchaseNumber = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $totalAmount += ((float) $item['quantity'] * (float) $item['unit_cost']);
            }

            $purchase = Purchase::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_number' => $purchaseNumber,
                'purchase_date' => $data['purchase_date'],
                'status' => PurchaseStatus::DRAFT,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $item) {
                $itemTotalCost = (float) $item['quantity'] * (float) $item['unit_cost'];
                PurchaseItem::create([
                    'id' => (string) Str::ulid(),
                    'purchase_id' => $purchase->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $itemTotalCost,
                ]);
            }

            return $purchase->load(['supplier', 'purchaseItems.ingredient', 'purchaseItems.unit']);
        });
    }

    public function receivePurchase(Purchase $purchase, ?string $userId = null): Purchase
    {
        if ($purchase->status !== PurchaseStatus::DRAFT) {
            throw new InvalidArgumentException('Only DRAFT purchases can be received.');
        }

        $storeId = $purchase->store_id;

        return DB::transaction(function () use ($purchase, $storeId, $userId) {
            foreach ($purchase->purchaseItems as $item) {
                // Lock stock record
                $stock = InventoryStock::withoutGlobalScope('store')
                    ->where('store_id', $storeId)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    $stock = InventoryStock::create([
                        'id' => (string) Str::ulid(),
                        'store_id' => $storeId,
                        'ingredient_id' => $item->ingredient_id,
                        'quantity' => 0,
                        'average_cost' => 0,
                    ]);
                }

                $oldQty = (float) $stock->quantity;
                $oldCost = (float) $stock->average_cost;
                $itemQty = (float) $item->quantity;
                $itemCost = (float) $item->unit_cost;

                $newQty = $oldQty + $itemQty;
                $newAvgCost = $newQty > 0 ? (($oldQty * $oldCost) + ($itemQty * $itemCost)) / $newQty : $itemCost;

                $stock->update([
                    'quantity' => $newQty,
                    'average_cost' => round($newAvgCost, 2),
                ]);

                // Record immutable movement
                InventoryMovement::create([
                    'id' => (string) Str::ulid(),
                    'store_id' => $storeId,
                    'ingredient_id' => $item->ingredient_id,
                    'unit_id' => $item->unit_id,
                    'type' => MovementType::PURCHASE,
                    'quantity' => $itemQty,
                    'reference_type' => 'PURCHASE',
                    'reference_id' => $purchase->id,
                    'notes' => 'Received from Purchase PO #' . $purchase->purchase_number,
                    'created_by' => $userId,
                ]);
            }

            $purchase->update(['status' => PurchaseStatus::RECEIVED]);

            return $purchase->fresh(['supplier', 'purchaseItems.ingredient', 'purchaseItems.unit']);
        });
    }

    public function cancelPurchase(Purchase $purchase): Purchase
    {
        if ($purchase->status !== PurchaseStatus::DRAFT) {
            throw new InvalidArgumentException('Only DRAFT purchases can be cancelled.');
        }

        $purchase->update(['status' => PurchaseStatus::CANCELLED]);

        return $purchase->fresh();
    }

    // ==========================================
    // STOCKS & ADJUSTMENTS
    // ==========================================

    public function listStocks(): Collection
    {
        return InventoryStock::with(['ingredient.unit'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getStock(string $ingredientId): InventoryStock
    {
        return InventoryStock::with(['ingredient.unit'])
            ->where('ingredient_id', $ingredientId)
            ->firstOrFail();
    }

    public function adjustStock(string $ingredientId, float $newQuantity, string $notes, ?string $userId = null): InventoryStock
    {
        $storeId = AppContext::storeId();

        if ($newQuantity < 0) {
            throw new InvalidArgumentException('Stock quantity cannot be negative.');
        }

        // Verify ingredient belongs to current store
        $ingredient = Ingredient::withoutGlobalScope('store')
            ->where('id', $ingredientId)
            ->where('store_id', $storeId)
            ->first();

        if (!$ingredient) {
            throw new InvalidArgumentException('The selected ingredient does not belong to the current store.');
        }

        return DB::transaction(function () use ($storeId, $ingredient, $ingredientId, $newQuantity, $notes, $userId) {
            $stock = InventoryStock::withoutGlobalScope('store')
                ->where('store_id', $storeId)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = InventoryStock::create([
                    'id' => (string) Str::ulid(),
                    'store_id' => $storeId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => 0,
                    'average_cost' => 0,
                ]);
            }

            $oldQty = (float) $stock->quantity;
            $diff = $newQuantity - $oldQty;

            if ($diff == 0) {
                throw new InvalidArgumentException('New quantity matches existing stock. No adjustment needed.');
            }

            $stock->update(['quantity' => $newQuantity]);

            InventoryMovement::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'ingredient_id' => $ingredientId,
                'unit_id' => $ingredient->unit_id,
                'type' => MovementType::ADJUSTMENT,
                'quantity' => $diff,
                'reference_type' => 'ADJUSTMENT',
                'reference_id' => $stock->id,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            return $stock->fresh(['ingredient.unit']);
        });
    }

    // ==========================================
    // MOVEMENTS (AUDIT LEDGER)
    // ==========================================

    public function listMovements(?string $ingredientId = null, ?string $type = null): LengthAwarePaginator
    {
        return InventoryMovement::with(['ingredient', 'unit'])
            ->when($ingredientId, fn ($q) => $q->where('ingredient_id', $ingredientId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    // ==========================================
    // WASTAGES
    // ==========================================

    public function listWastages(): Collection
    {
        return Wastage::with(['wastageItems.ingredient', 'wastageItems.unit'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createWastage(array $data, ?string $userId = null): Wastage
    {
        $storeId = AppContext::storeId();

        // Cross-store validation: All ingredients must belong to current store
        $ingredientIds = collect($data['items'])->pluck('ingredient_id')->unique()->all();
        $validIngredientsCount = Ingredient::withoutGlobalScope('store')
            ->whereIn('id', $ingredientIds)
            ->where('store_id', $storeId)
            ->count();

        if ($validIngredientsCount !== count($ingredientIds)) {
            throw new InvalidArgumentException('One or more selected ingredients do not belong to the current store.');
        }

        return DB::transaction(function () use ($data, $storeId, $userId) {
            $wastageNumber = 'WST-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $wastage = Wastage::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'wastage_number' => $wastageNumber,
                'wastage_date' => $data['wastage_date'],
                'reason' => $data['reason'],
                'status' => WastageStatus::COMPLETED,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $item) {
                // Lock stock
                $stock = InventoryStock::withoutGlobalScope('store')
                    ->where('store_id', $storeId)
                    ->where('ingredient_id', $item['ingredient_id'])
                    ->lockForUpdate()
                    ->first();

                $currentQty = $stock ? (float) $stock->quantity : 0;
                $wasteQty = (float) $item['quantity'];

                if ($currentQty < $wasteQty) {
                    throw new InvalidArgumentException("Insufficient stock for ingredient {$item['ingredient_id']} to record wastage.");
                }

                $unitCost = $stock ? (float) $stock->average_cost : 0;
                $totalCost = $wasteQty * $unitCost;

                $stock->decrement('quantity', $wasteQty);

                WastageItem::create([
                    'id' => (string) Str::ulid(),
                    'wastage_id' => $wastage->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $wasteQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                ]);

                // Record immutable movement (negative quantity)
                InventoryMovement::create([
                    'id' => (string) Str::ulid(),
                    'store_id' => $storeId,
                    'ingredient_id' => $item['ingredient_id'],
                    'unit_id' => $item['unit_id'],
                    'type' => MovementType::WASTAGE,
                    'quantity' => -1 * $wasteQty,
                    'reference_type' => 'WASTAGE',
                    'reference_id' => $wastage->id,
                    'notes' => 'Wastage: ' . $data['reason'],
                    'created_by' => $userId,
                ]);
            }

            return $wastage->load(['wastageItems.ingredient', 'wastageItems.unit']);
        });
    }
}
