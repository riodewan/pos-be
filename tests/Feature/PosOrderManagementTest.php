<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\MovementType;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\UnitType;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\RecipeMenu\Models\RecipeItem;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOrderManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $manager;
    protected User $cashier;
    protected Product $productAmericano;
    protected Product $productLatte;
    protected Product $productStore2;
    protected Ingredient $coffeeBeans;
    protected Unit $unitGram;
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
            'name' => 'Kopi Kita Sejahtera',
            'slug' => 'kopi-kita-' . Str::random(4),
            'email' => 'kks_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Stores
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet SCBD',
            'code' => 'SCBD-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->store2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Kemang',
            'code' => 'KMG-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Create Manager (full store operations)
        $this->manager = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Store Manager',
            'email' => 'mgr_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->manager->id,
        ]);

        $this->rbacService->assignRole($this->manager, $this->roles['Manager'], $this->tenant->id, null);

        // Create Cashier (Store 1 POS & Payments)
        $this->cashier = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kasir Sore',
            'email' => 'kasir_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
        ]);

        $this->rbacService->assignRole($this->cashier, $this->roles['Cashier'], $this->tenant->id, $this->store1->id);

        // Unit Gram
        $this->unitGram = Unit::firstOrCreate(
            ['code' => 'GR'],
            ['id' => (string) Str::ulid(), 'name' => 'Gram', 'type' => UnitType::WEIGHT]
        );

        // Store 1 Products
        $this->productAmericano = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Iced Americano',
            'sku' => 'AME-01',
            'selling_price' => 28000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->productLatte = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Caffe Latte',
            'sku' => 'LAT-01',
            'selling_price' => 35000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Store 2 Product (for cross-store isolation tests)
        $this->productStore2 = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'name' => 'Matcha Latte Kemang',
            'sku' => 'MTC-02',
            'selling_price' => 38000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Ingredient for Store 1
        $this->coffeeBeans = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'unit_id' => $this->unitGram->id,
            'name' => 'Arabica Gayo Beans',
            'sku' => 'ING-GY-01',
            'cost_per_unit' => 250.00,
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    public function test_create_table_with_store_scoping_and_auto_qr_generation(): void
    {
        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/tables', [
                'name' => 'Meja VIP 01',
                'code' => 'T-VIP-1',
                'capacity' => 6,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Meja VIP 01')
            ->assertJsonPath('data.code', 'T-VIP-1')
            ->assertJsonPath('data.capacity', 6)
            ->assertJsonPath('data.status', 'ACTIVE');

        $tableId = $response->json('data.id');

        // Verify QR code was automatically generated and active
        $this->assertDatabaseHas('qr_codes', [
            'table_id' => $tableId,
            'store_id' => $this->store1->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_list_tables_computes_operational_occupancy(): void
    {
        // Create 2 tables
        $table1 = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja 01',
            'code' => 'M01',
            'capacity' => 2,
            'status' => 'ACTIVE',
        ]);

        $table2 = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja 02',
            'code' => 'M02',
            'capacity' => 4,
            'status' => 'ACTIVE',
        ]);

        // Place active order on Table 1
        Order::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'table_id' => $table1->id,
            'order_number' => 'ORD-TEST-01',
            'status' => OrderStatus::PENDING,
            'subtotal' => 28000,
            'total_amount' => 28000,
        ]);

        $response = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/pos/tables');

        $response->assertStatus(200);

        $tablesData = collect($response->json('data'))->keyBy('code');

        // Table 1 has active order -> occupied = true
        $this->assertTrue($tablesData->get('M01')['is_occupied']);
        $this->assertEquals(1, $tablesData->get('M01')['active_orders_count']);

        // Table 2 has no orders -> occupied = false
        $this->assertFalse($tablesData->get('M02')['is_occupied']);
        $this->assertEquals(0, $tablesData->get('M02')['active_orders_count']);
    }

    public function test_rotate_qr_code_deactivates_previous_qr(): void
    {
        $table = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja Outdoor 03',
            'code' => 'MO-03',
            'capacity' => 4,
            'status' => 'ACTIVE',
        ]);

        $oldQr = QrCode::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'table_id' => $table->id,
            'token' => 'OLD-TOKEN-' . Str::random(20),
            'status' => QrStatus::ACTIVE,
        ]);

        // Manager generates / rotates new QR
        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/tables/{$table->id}/qr");

        $response->assertStatus(200);
        $newQrId = $response->json('data.id');

        $this->assertNotEquals($oldQr->id, $newQrId);

        // Verify previous QR is now INACTIVE
        $this->assertDatabaseHas('qr_codes', [
            'id' => $oldQr->id,
            'status' => 'INACTIVE',
        ]);

        // Verify new QR is ACTIVE
        $this->assertDatabaseHas('qr_codes', [
            'id' => $newQrId,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_public_qr_resolution_and_customer_ordering(): void
    {
        $table = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja Bar 05',
            'code' => 'BAR-05',
            'capacity' => 2,
            'status' => 'ACTIVE',
        ]);

        $token = 'SECURE-QR-TOKEN-FOR-TESTING-' . Str::random(10);
        QrCode::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'table_id' => $table->id,
            'token' => $token,
            'status' => QrStatus::ACTIVE,
        ]);

        // Public resolution (No headers, no auth)
        $resolveResponse = $this->getJson("/api/public/qr/{$token}");

        $resolveResponse->assertStatus(200)
            ->assertJsonPath('data.store.id', $this->store1->id)
            ->assertJsonPath('data.table.id', $table->id)
            ->assertJsonPath('data.table.code', 'BAR-05');

        // Customer places order via QR
        $orderResponse = $this->postJson("/api/public/qr/{$token}/order", [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'notes' => 'Less sugar please',
            'items' => [
                [
                    'product_id' => $this->productAmericano->id,
                    'quantity' => 2,
                    'notes' => 'Ice separate',
                ],
            ],
        ]);

        $orderResponse->assertStatus(201)
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonPath('data.table_id', $table->id)
            ->assertJsonPath('data.source', 'QR')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.subtotal', '56000.00')
            ->assertJsonPath('data.total_amount', '56000.00');

        $this->assertDatabaseHas('orders', [
            'store_id' => $this->store1->id,
            'table_id' => $table->id,
            'source' => 'QR',
            'customer_name' => 'Budi Santoso',
            'total_amount' => 56000.00,
        ]);
    }

    public function test_customer_qr_direct_payment_marks_order_paid_and_preparing(): void
    {
        $table = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja Bar 06',
            'code' => 'BAR-06',
            'capacity' => 2,
            'status' => 'ACTIVE',
        ]);

        $token = 'DIRECT-PAY-QR-' . Str::random(10);
        QrCode::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'table_id' => $table->id,
            'token' => $token,
            'status' => QrStatus::ACTIVE,
        ]);

        // Customer orders and pays directly via QRIS
        $response = $this->postJson("/api/public/qr/{$token}/order", [
            'customer_name' => 'Siti Rahma',
            'customer_phone' => '081987654321',
            'payment_method' => 'QRIS',
            'items' => [
                [
                    'product_id' => $this->productAmericano->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'PREPARING')
            ->assertJsonPath('data.source', 'QR');

        $orderId = $response->json('data.id');

        // Verify successful payment record exists
        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        // Cafe staff prepares order (READY) and closes it (COMPLETED)
        $readyRes = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->putJson("/api/pos/orders/{$orderId}/status", [
                'status' => 'READY',
            ]);
        $readyRes->assertStatus(200)->assertJsonPath('data.status', 'READY');

        // Close order
        $completeRes = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->putJson("/api/pos/orders/{$orderId}/status", [
                'status' => 'COMPLETED',
            ]);
        $completeRes->assertStatus(200)->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_cashier_create_pos_order_with_cross_store_protection(): void
    {
        $tableStore1 = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja Kasir 01',
            'code' => 'K01',
            'status' => 'ACTIVE',
        ]);

        $tableStore2 = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'name' => 'Meja Kemang 01',
            'code' => 'KM01',
            'status' => 'ACTIVE',
        ]);

        // 1. Cross-store table attempt -> Rejected
        $responseBadTable = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/orders', [
                'table_id' => $tableStore2->id,
                'items' => [
                    ['product_id' => $this->productAmericano->id, 'quantity' => 1],
                ],
            ]);

        $responseBadTable->assertStatus(422);

        // 2. Cross-store product attempt -> Rejected
        $responseBadProduct = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/orders', [
                'table_id' => $tableStore1->id,
                'items' => [
                    ['product_id' => $this->productStore2->id, 'quantity' => 1],
                ],
            ]);

        $responseBadProduct->assertStatus(422);

        // 3. Valid POS order with calculations
        $responseValid = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/orders', [
                'table_id' => $tableStore1->id,
                'customer_name' => 'Walk-in Cashier',
                'discount_amount' => 5000.00,
                'tax_amount' => 2000.00,
                'service_charge' => 1000.00,
                'items' => [
                    [
                        'product_id' => $this->productAmericano->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $this->productLatte->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        // Subtotal: (2 * 28,000) + (1 * 35,000) = 56,000 + 35,000 = 91,000
        // Total: 91,000 - 5,000 + 2,000 + 1,000 = 89,000
        $responseValid->assertStatus(201)
            ->assertJsonPath('data.subtotal', '91000.00')
            ->assertJsonPath('data.discount_amount', '5000.00')
            ->assertJsonPath('data.total_amount', '89000.00')
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_order_status_transitions_and_cancellation(): void
    {
        $order = Order::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'order_number' => 'ORD-STATUS-' . Str::random(4),
            'status' => OrderStatus::PENDING,
            'subtotal' => 28000,
            'total_amount' => 28000,
        ]);

        // Transition PENDING -> PREPARING
        $resPrep = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->putJson("/api/pos/orders/{$order->id}/status", [
                'status' => 'PREPARING',
            ]);

        $resPrep->assertStatus(200)
            ->assertJsonPath('data.status', 'PREPARING');

        // Cancel order
        $resCancel = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/orders/{$order->id}/cancel", [
                'reason' => 'Customer changed mind',
            ]);

        $resCancel->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        // Attempting to change status of cancelled order fails
        $resForbidden = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->putJson("/api/pos/orders/{$order->id}/status", [
                'status' => 'READY',
            ]);

        $resForbidden->assertStatus(422);
    }

    public function test_process_payment_depletes_recipe_inventory_and_completes_order(): void
    {
        // 1. Setup Recipe: Americano uses 18 grams of Coffee Beans
        $recipe = Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'product_id' => $this->productAmericano->id,
            'name' => 'Standard Double Shot Recipe',
            'status' => CommonStatus::ACTIVE,
        ]);

        RecipeItem::create([
            'id' => (string) Str::ulid(),
            'recipe_id' => $recipe->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'unit_id' => $this->unitGram->id,
            'quantity' => 18.0000,
        ]);

        // 2. Setup initial stock: 1,000 grams of Coffee Beans
        $initialStock = InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 1000.0000,
            'average_cost' => 250.00,
        ]);

        // 3. Table and Order for 2 Americano (requires 2 * 18g = 36g)
        $table = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Meja Pelanggan 08',
            'code' => 'P08',
            'status' => 'ACTIVE',
        ]);

        $orderResponse = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/orders', [
                'table_id' => $table->id,
                'items' => [
                    ['product_id' => $this->productAmericano->id, 'quantity' => 2],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');
        $totalAmount = (float) $orderResponse->json('data.total_amount'); // 56,000

        // 4. Process Payment with cash 60,000 (change = 4,000)
        $paymentResponse = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/orders/{$orderId}/payments", [
                'method' => 'CASH',
                'amount' => 60000.00,
            ]);

        $paymentResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'SUCCESS')
            ->assertJsonPath('data.method', 'CASH')
            ->assertJsonPath('data.metadata.change', 4000);

        // 5. Verify order is now COMPLETED
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'COMPLETED',
        ]);

        // 6. Verify inventory stock depleted by 36g (1000 - 36 = 964g)
        $this->assertDatabaseHas('inventory_stocks', [
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 964.0000,
        ]);

        // 7. Verify inventory movement recorded with SALE and negative quantity (-36)
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'type' => MovementType::SALE->value,
            'quantity' => -36.0000,
            'reference_type' => 'ORDER',
            'reference_id' => $orderId,
        ]);
    }

    public function test_process_payment_rejects_insufficient_stock(): void
    {
        // Recipe requires 18g
        $recipe = Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'product_id' => $this->productAmericano->id,
            'name' => 'Standard Recipe',
            'status' => CommonStatus::ACTIVE,
        ]);

        RecipeItem::create([
            'id' => (string) Str::ulid(),
            'recipe_id' => $recipe->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'unit_id' => $this->unitGram->id,
            'quantity' => 18.0000,
        ]);

        // Stock has only 10 grams (insufficient for 18g order)
        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 10.0000,
            'average_cost' => 250.00,
        ]);

        $orderResponse = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson('/api/pos/orders', [
                'items' => [
                    ['product_id' => $this->productAmericano->id, 'quantity' => 1],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');

        // Payment should be rejected with 422 Insufficient stock
        $paymentResponse = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/orders/{$orderId}/payments", [
                'method' => 'CASH',
                'amount' => 28000.00,
            ]);

        $paymentResponse->assertStatus(422)
            ->assertJsonFragment(['message' => "Insufficient stock for ingredient 'Arabica Gayo Beans' to fulfill order."]);
    }

    public function test_single_success_payment_per_order_enforced(): void
    {
        $order = Order::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'order_number' => 'ORD-PAY-TEST',
            'status' => OrderStatus::PENDING,
            'subtotal' => 28000,
            'total_amount' => 28000,
        ]);

        // Process first payment successfully
        $res1 = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/orders/{$order->id}/payments", [
                'method' => 'QRIS',
                'amount' => 28000.00,
            ]);

        $res1->assertStatus(201);

        // Attempt second payment on same order -> Rejected with 422
        $res2 = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->postJson("/api/pos/orders/{$order->id}/payments", [
                'method' => 'CASH',
                'amount' => 28000.00,
            ]);

        $res2->assertStatus(422);
    }
}
