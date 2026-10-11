<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\UnitType;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $inventoryStaff;
    protected Supplier $supplierStore1;
    protected Supplier $supplierStore2;
    protected Ingredient $coffeeBeans;
    protected Ingredient $freshMilk;
    protected Unit $unitKg;
    protected Unit $unitLiter;
    protected array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        AppContext::clear();
        $this->rbacService = app(RbacService::class);
        $this->seed(RbacSeeder::class);

        // Create Tenant
        $this->tenant = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kopi Lumbung Padi',
            'slug' => 'kopi-lumbung-' . Str::random(4),
            'email' => 'klp_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Stores
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Senayan',
            'code' => 'SNY-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->store2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet BSD',
            'code' => 'BSD-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Create Inventory Staff (Tenant-wide)
        $this->inventoryStaff = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Staff Gudang Logistik',
            'email' => 'gudang_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->inventoryStaff->id,
        ]);

        $this->rbacService->assignRole($this->inventoryStaff, $this->roles['Inventory'], $this->tenant->id, null);

        // Units
        $this->unitKg = Unit::firstOrCreate(
            ['code' => 'KG'],
            ['id' => (string) Str::ulid(), 'name' => 'Kilogram', 'type' => UnitType::WEIGHT]
        );

        $this->unitLiter = Unit::firstOrCreate(
            ['code' => 'LTR'],
            ['id' => (string) Str::ulid(), 'name' => 'Liter', 'type' => UnitType::VOLUME]
        );

        // Suppliers
        $this->supplierStore1 = Supplier::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Supplier Biji Kopi S1',
            'code' => 'SUP-S1-' . Str::random(3),
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->supplierStore2 = Supplier::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'name' => 'Supplier Susu BSD S2',
            'code' => 'SUP-S2-' . Str::random(3),
            'status' => CommonStatus::ACTIVE,
        ]);

        // Ingredients
        $this->coffeeBeans = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'unit_id' => $this->unitKg->id,
            'name' => 'Biji Kopi Arabica Espresso',
            'sku' => 'ING-CF-01',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->freshMilk = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'unit_id' => $this->unitLiter->id,
            'name' => 'Susu Pasteurisasi',
            'sku' => 'ING-MK-01',
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    public function test_create_purchase_order_in_draft_status(): void
    {
        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/purchases', [
                'supplier_id' => $this->supplierStore1->id,
                'purchase_date' => '2026-10-10',
                'notes' => 'Pembelian mingguan bahan baku',
                'items' => [
                    [
                        'ingredient_id' => $this->coffeeBeans->id,
                        'unit_id' => $this->unitKg->id,
                        'quantity' => 10,
                        'unit_cost' => 150000,
                    ],
                    [
                        'ingredient_id' => $this->freshMilk->id,
                        'unit_id' => $this->unitLiter->id,
                        'quantity' => 20,
                        'unit_cost' => 20000,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total_amount', 1900000)
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('purchases', [
            'store_id' => $this->store1->id,
            'status' => 'DRAFT',
            'total_amount' => 1900000,
        ]);
    }

    public function test_create_purchase_rejects_cross_store_supplier(): void
    {
        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/purchases', [
                'supplier_id' => $this->supplierStore2->id, // Belongs to Store 2!
                'purchase_date' => '2026-10-10',
                'items' => [
                    [
                        'ingredient_id' => $this->coffeeBeans->id,
                        'unit_id' => $this->unitKg->id,
                        'quantity' => 5,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The selected supplier does not belong to the current store.');
    }

    public function test_receive_purchase_order_updates_stock_and_calculates_average_cost(): void
    {
        // 1. Initial stock: 5 kg @ 100.000
        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 5,
            'average_cost' => 100000,
        ]);

        // 2. Create Purchase of 5 kg @ 200.000
        $createResp = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/purchases', [
                'supplier_id' => $this->supplierStore1->id,
                'purchase_date' => '2026-10-10',
                'items' => [
                    [
                        'ingredient_id' => $this->coffeeBeans->id,
                        'unit_id' => $this->unitKg->id,
                        'quantity' => 5,
                        'unit_cost' => 200000,
                    ],
                ],
            ]);

        $purchaseId = $createResp->json('data.id');

        // 3. Receive Purchase
        $receiveResp = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson("/api/inventory/purchases/{$purchaseId}/receive");

        $receiveResp->assertStatus(200)
            ->assertJsonPath('data.status', 'RECEIVED');

        // 4. Verify moving average: ((5 * 100.000) + (5 * 200.000)) / 10 = 150.000
        $stock = InventoryStock::where('store_id', $this->store1->id)
            ->where('ingredient_id', $this->coffeeBeans->id)
            ->first();

        $this->assertEquals(10, (float) $stock->quantity);
        $this->assertEquals(150000, (float) $stock->average_cost);

        // 5. Verify immutable inventory movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'type' => 'PURCHASE',
            'quantity' => 5,
            'reference_type' => 'PURCHASE',
            'reference_id' => $purchaseId,
        ]);
    }

    public function test_cancel_purchase_order(): void
    {
        $createResp = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/purchases', [
                'purchase_date' => '2026-10-10',
                'items' => [
                    [
                        'ingredient_id' => $this->coffeeBeans->id,
                        'unit_id' => $this->unitKg->id,
                        'quantity' => 2,
                        'unit_cost' => 100000,
                    ],
                ],
            ]);

        $purchaseId = $createResp->json('data.id');

        $cancelResp = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson("/api/inventory/purchases/{$purchaseId}/cancel");

        $cancelResp->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_adjust_stock_updates_balance_and_records_audit_movement(): void
    {
        // 1. Initial stock: 10
        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 10,
            'average_cost' => 150000,
        ]);

        // 2. Opname shows actual stock is 8.5 kg (-1.5 adjustment)
        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/adjustments', [
                'ingredient_id' => $this->coffeeBeans->id,
                'new_quantity' => 8.5,
                'notes' => 'Stock opname akhir bulan',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 8.5);

        // 3. Verify movement: diff = -1.5
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'type' => 'ADJUSTMENT',
            'quantity' => -1.5,
            'notes' => 'Stock opname akhir bulan',
        ]);
    }

    public function test_record_wastage_decrements_stock_and_records_negative_movement(): void
    {
        // 1. Initial stock: 10 L
        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->freshMilk->id,
            'quantity' => 10,
            'average_cost' => 20000,
        ]);

        // 2. Record wastage of 2 L (spilled)
        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/wastages', [
                'wastage_date' => '2026-10-10',
                'reason' => 'Susu tumpah saat persiapan',
                'items' => [
                    [
                        'ingredient_id' => $this->freshMilk->id,
                        'unit_id' => $this->unitLiter->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.reason', 'Susu tumpah saat persiapan');

        // 3. Stock should now be 8 L
        $stock = InventoryStock::where('store_id', $this->store1->id)
            ->where('ingredient_id', $this->freshMilk->id)
            ->first();

        $this->assertEquals(8, (float) $stock->quantity);

        // 4. Verify movement: quantity = -2
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->freshMilk->id,
            'type' => 'WASTAGE',
            'quantity' => -2,
        ]);
    }

    public function test_record_wastage_fails_when_stock_insufficient(): void
    {
        // Available stock: only 1 L
        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->freshMilk->id,
            'quantity' => 1,
            'average_cost' => 20000,
        ]);

        // Try wasting 5 L
        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/wastages', [
                'wastage_date' => '2026-10-10',
                'reason' => 'Basi',
                'items' => [
                    [
                        'ingredient_id' => $this->freshMilk->id,
                        'unit_id' => $this->unitLiter->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_list_movements_audit_ledger(): void
    {
        // Perform 1 adjustment
        $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/inventory/adjustments', [
                'ingredient_id' => $this->coffeeBeans->id,
                'new_quantity' => 12,
                'notes' => 'Awal bulan',
            ]);

        $response = $this->actingAs($this->inventoryStaff)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->getJson('/api/inventory/movements');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.type', 'ADJUSTMENT');
    }
}
