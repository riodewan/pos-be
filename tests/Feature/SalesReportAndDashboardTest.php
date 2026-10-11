<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\MovementType;
use App\Core\Enums\OrderSource;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\UnitType;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\OrderItem;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReportAndDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $manager;
    protected User $cashier1;
    protected User $cashier2;
    protected Product $productAmericano;
    protected Product $productLatte;
    protected Product $productCroissant;
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
            'name' => 'Kopi Mentari Pagi',
            'slug' => 'mentari-' . Str::random(4),
            'email' => 'mentari_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Stores
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Tebet',
            'code' => 'TBT-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->store2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Rawamangun',
            'code' => 'RWM-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Manager
        $this->manager = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Operational Manager',
            'email' => 'ops_' . Str::random(5) . '@tenant.com',
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

        // Cashier 1
        $this->cashier1 = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kasir Shift Pagi',
            'email' => 'kasir1_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier1->id,
        ]);

        $this->rbacService->assignRole($this->cashier1, $this->roles['Cashier'], $this->tenant->id, $this->store1->id);

        // Cashier 2
        $this->cashier2 = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kasir Shift Malam',
            'email' => 'kasir2_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier2->id,
        ]);

        $this->rbacService->assignRole($this->cashier2, $this->roles['Cashier'], $this->tenant->id, $this->store1->id);

        // Units & Products
        $this->unitGram = Unit::firstOrCreate(
            ['code' => 'GR'],
            ['id' => (string) Str::ulid(), 'name' => 'Gram', 'type' => UnitType::WEIGHT]
        );

        $this->productAmericano = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Iced Americano',
            'sku' => 'AME-TBT',
            'selling_price' => 30000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->productLatte = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Hot Latte',
            'sku' => 'LAT-TBT',
            'selling_price' => 35000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->productCroissant = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Butter Croissant',
            'sku' => 'CRS-TBT',
            'selling_price' => 25000.00,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->coffeeBeans = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'unit_id' => $this->unitGram->id,
            'name' => 'Espresso Beans Blend',
            'sku' => 'ING-ESP-01',
            'status' => CommonStatus::ACTIVE,
        ]);

        InventoryStock::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'quantity' => 5000.0000,
            'average_cost' => 200.00,
        ]);
    }

    /**
     * Helper to create completed order with items and payment.
     */
    protected function createCompletedOrder(
        Store $store,
        User $cashier,
        array $items,
        string $paymentMethod = 'CASH',
        float $discount = 0.0,
        float $tax = 0.0,
        float $service = 0.0,
        float $cashChange = 0.0,
        ?Carbon $createdAt = null
    ): Order {
        $createdAt = $createdAt ?? Carbon::now();

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ($item['quantity'] * $item['product']->selling_price);
        }

        $totalAmount = max(0, $subtotal - $discount + $tax + $service);

        $order = Order::create([
            'id' => (string) Str::ulid(),
            'store_id' => $store->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(6)),
            'source' => OrderSource::POS,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'service_charge' => $service,
            'total_amount' => $totalAmount,
            'created_by' => $cashier->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items as $item) {
            $itemSubtotal = $item['quantity'] * $item['product']->selling_price;
            OrderItem::create([
                'id' => (string) Str::ulid(),
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['product']->selling_price,
                'discount_amount' => 0,
                'subtotal' => $itemSubtotal,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        Payment::create([
            'id' => (string) Str::ulid(),
            'order_id' => $order->id,
            'payment_number' => 'PAY-' . strtoupper(Str::random(6)),
            'method' => PaymentMethod::from($paymentMethod),
            'amount' => $totalAmount + $cashChange,
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => $createdAt,
            'metadata' => ['change' => $cashChange],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
        \Illuminate\Support\Facades\DB::table('payments')->where('order_id', $order->id)->update(['created_at' => $createdAt, 'paid_at' => $createdAt]);

        return $order;
    }

    public function test_dashboard_overview_returns_aggregated_kpis_trend_top_products_and_payments(): void
    {
        // Order 1: 2 Americanos (60k) + Cash (change 10k)
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productAmericano, 'quantity' => 2]],
            paymentMethod: 'CASH',
            discount: 5000,
            tax: 5500,
            cashChange: 10000
        );

        // Order 2: 1 Latte (35k) + 2 Croissants (50k) = 85k QRIS
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier2,
            [
                ['product' => $this->productLatte, 'quantity' => 1],
                ['product' => $this->productCroissant, 'quantity' => 2],
            ],
            paymentMethod: 'QRIS'
        );

        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'period' => ['start_date', 'end_date'],
                    'kpis' => [
                        'total_orders',
                        'gross_sales',
                        'total_discounts',
                        'net_sales',
                        'total_tax',
                        'total_service',
                        'total_revenue',
                        'average_order_value',
                    ],
                    'sales_trend',
                    'top_products',
                    'payment_methods',
                ],
            ]);

        $kpis = $response->json('data.kpis');
        $this->assertEquals(2, $kpis['total_orders']);
        // Order 1 gross: 60,000; Order 2 gross: 85,000 => 145,000
        $this->assertEquals(145000, $kpis['gross_sales']);
        $this->assertEquals(5000, $kpis['total_discounts']);
        $this->assertEquals(140000, $kpis['net_sales']);
        $this->assertEquals(5500, $kpis['total_tax']);
        // Order 1 total: 60,000 - 5,000 + 5,500 = 60,500; Order 2 total: 85,000 => 145,500
        $this->assertEquals(145500, $kpis['total_revenue']);
        $this->assertEquals(72750, $kpis['average_order_value']);
    }

    public function test_sales_summary_and_daily_trend_with_date_filtering(): void
    {
        $date1 = Carbon::parse('2026-10-01 14:00:00');
        $date2 = Carbon::parse('2026-10-02 15:00:00');
        $dateOld = Carbon::parse('2026-09-15 10:00:00'); // outside filter

        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productAmericano, 'quantity' => 1]],
            createdAt: $date1
        );

        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productLatte, 'quantity' => 2]],
            createdAt: $date2
        );

        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productCroissant, 'quantity' => 5]],
            createdAt: $dateOld
        );

        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/sales?start_date=2026-10-01&end_date=2026-10-02');

        $response->assertStatus(200);

        // Only 2 orders within range (not the September order)
        $this->assertEquals(2, $response->json('data.kpis.total_orders'));

        $trend = $response->json('data.trend');
        $this->assertCount(2, $trend);
        $this->assertEquals('2026-10-01', $trend[0]['date']);
        $this->assertEquals(30000, $trend[0]['gross_sales']);
        $this->assertEquals('2026-10-02', $trend[1]['date']);
        $this->assertEquals(70000, $trend[1]['gross_sales']);
    }

    public function test_top_products_report_ranks_by_quantity_sold(): void
    {
        // 5 Croissants, 3 Americanos, 1 Latte
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [
                ['product' => $this->productCroissant, 'quantity' => 5],
                ['product' => $this->productAmericano, 'quantity' => 3],
                ['product' => $this->productLatte, 'quantity' => 1],
            ]
        );

        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/products');

        $response->assertStatus(200);

        $products = $response->json('data');
        $this->assertCount(3, $products);

        // Rank 1: Croissant (quantity = 5)
        $this->assertEquals('Butter Croissant', $products[0]['product_name']);
        $this->assertEquals(5, $products[0]['quantity_sold']);

        // Rank 2: Americano (quantity = 3)
        $this->assertEquals('Iced Americano', $products[1]['product_name']);
        $this->assertEquals(3, $products[1]['quantity_sold']);

        // Rank 3: Latte (quantity = 1)
        $this->assertEquals('Hot Latte', $products[2]['product_name']);
        $this->assertEquals(1, $products[2]['quantity_sold']);
    }

    public function test_payment_methods_breakdown_calculates_totals_and_percentages(): void
    {
        // Cash: 50,000
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productCroissant, 'quantity' => 2]],
            paymentMethod: 'CASH'
        );

        // QRIS: 150,000
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier2,
            [['product' => $this->productAmericano, 'quantity' => 5]],
            paymentMethod: 'QRIS'
        );

        // Total payments = 200,000. QRIS = 75%, CASH = 25%
        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/payments');

        $response->assertStatus(200);

        $methods = collect($response->json('data'))->keyBy('method');

        $this->assertEquals(150000, $methods->get('QRIS')['total_amount']);
        $this->assertEquals(75.0, $methods->get('QRIS')['percentage']);

        $this->assertEquals(50000, $methods->get('CASH')['total_amount']);
        $this->assertEquals(25.0, $methods->get('CASH')['percentage']);
    }

    public function test_inventory_consumption_and_cogs_financial_analysis(): void
    {
        // Order 1: 100,000 net sales
        $order = $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productAmericano, 'quantity' => 3]],
            paymentMethod: 'QRIS'
        );

        // Record ingredient consumption (e.g. 54g Coffee Beans used)
        // Unit cost is 200 => COGS = 54 * 200 = 10,800
        InventoryMovement::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'ingredient_id' => $this->coffeeBeans->id,
            'unit_id' => $this->unitGram->id,
            'type' => MovementType::SALE,
            'quantity' => -54.0000,
            'reference_type' => 'ORDER',
            'reference_id' => $order->id,
            'notes' => 'Sale consumption',
            'created_by' => $this->cashier1->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/inventory-usage');

        $response->assertStatus(200);

        $fin = $response->json('data.financial_analysis');
        $this->assertEquals(90000, $fin['net_sales']); // 3 * 30,000 = 90,000
        $this->assertEquals(10800, $fin['total_cogs']); // 54g * 200 = 10,800
        $this->assertEquals(79200, $fin['gross_profit']); // 90,000 - 10,800 = 79,200
        $this->assertEquals(88.0, $fin['gross_profit_margin_percentage']); // (79,200 / 90,000) * 100 = 88%

        $items = $response->json('data.items');
        $this->assertEquals('Espresso Beans Blend', $items[0]['ingredient_name']);
        $this->assertEquals(54, $items[0]['total_consumed_quantity']);
    }

    public function test_cashier_shift_report_audits_cash_collected_and_digital_payments(): void
    {
        // Cashier 1: Handled 60k order paid with 70k Cash (change 10k -> net cash 60k)
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productAmericano, 'quantity' => 2]],
            paymentMethod: 'CASH',
            cashChange: 10000
        );

        // Cashier 2: Handled 70k order paid with QRIS
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier2,
            [['product' => $this->productLatte, 'quantity' => 2]],
            paymentMethod: 'QRIS'
        );

        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store1->id,
            ])
            ->getJson('/api/reports/cashiers');

        $response->assertStatus(200);

        $cashiers = collect($response->json('data.cashiers'))->keyBy('user_id');

        $shift1 = $cashiers->get($this->cashier1->id);
        $this->assertEquals('Kasir Shift Pagi', $shift1['user_name']);
        $this->assertEquals(1, $shift1['total_orders']);
        $this->assertEquals(60000, $shift1['cash_total']);
        $this->assertEquals(0, $shift1['qris_total']);

        $shift2 = $cashiers->get($this->cashier2->id);
        $this->assertEquals('Kasir Shift Malam', $shift2['user_name']);
        $this->assertEquals(1, $shift2['total_orders']);
        $this->assertEquals(0, $shift2['cash_total']);
        $this->assertEquals(70000, $shift2['qris_total']);
    }

    public function test_cross_store_reporting_isolation(): void
    {
        // Create sale in Store 1
        $this->createCompletedOrder(
            $this->store1,
            $this->cashier1,
            [['product' => $this->productAmericano, 'quantity' => 1]]
        );

        // Query reports under Store 2 context -> Must have 0 sales
        $response = $this->actingAs($this->manager)
            ->withHeaders([
                'X-Tenant-Id' => $this->tenant->id,
                'X-Store-Id' => $this->store2->id,
            ])
            ->getJson('/api/reports/sales');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.total_orders', 0)
            ->assertJsonPath('data.kpis.gross_sales', 0)
            ->assertJsonPath('data.kpis.total_revenue', 0);
    }
}
