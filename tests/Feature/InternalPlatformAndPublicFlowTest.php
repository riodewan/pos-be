<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\OrderItem;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalPlatformAndPublicFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $regularUser;
    protected Tenant $tenant;
    protected Store $store;
    protected Table $table;
    protected QrCode $qrCode;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        AppContext::clear();
        $this->seed(RbacSeeder::class);

        // 1. Internal Super Admin (Platform)
        $this->superAdmin = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Internal Super Admin',
            'email' => 'superadmin@pos-platform.com',
            'password' => 'secret123',
            'is_internal' => true,
            'is_active' => true,
        ]);

        // 2. Regular Non-Internal User
        $this->regularUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Store Regular User',
            'email' => 'staff@store.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        // 3. Tenant & Store
        $this->tenant = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kopi Nusantara Test',
            'slug' => 'kopi-nusantara-test',
            'email' => 'test@kopinusantara.com',
            'status' => 'ACTIVE',
        ]);

        $this->store = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Senopati Branch',
            'code' => 'SNP-01',
            'status' => CommonStatus::ACTIVE,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->regularUser->id,
            'joined_at' => now(),
        ]);

        // 4. Table & QR for Customer Flow
        $this->table = Table::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store->id,
            'name' => 'Meja 01',
            'code' => 'M01',
            'capacity' => 4,
            'status' => TableStatus::ACTIVE,
        ]);

        $this->qrCode = QrCode::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store->id,
            'table_id' => $this->table->id,
            'token' => 'TEST-TOKEN-PUBLIC-12345',
            'status' => QrStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store->id,
            'name' => 'Latte Macchiato',
            'sku' => 'LATTE-01',
            'selling_price' => 35000,
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    public function test_internal_overview_returns_platform_kpis(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/internal/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_tenants',
                        'active_tenants',
                        'total_stores',
                        'total_orders',
                        'total_gmv',
                    ],
                    'recent_tenants',
                ],
            ]);
    }

    public function test_internal_routes_forbidden_for_non_internal_user(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/internal/overview');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Access restricted to internal platform administrators only.');
    }

    public function test_internal_admin_can_list_and_create_tenants(): void
    {
        // List tenants
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/internal/tenants');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));

        // Create new tenant directly via internal API
        $createResponse = $this->actingAs($this->superAdmin)
            ->postJson('/api/internal/tenants', [
                'name' => 'Resto Sedap Malam',
                'email' => 'sedapmalam@resto.com',
                'phone' => '021-998877',
                'owner_name' => 'Pak Budi',
                'owner_email' => 'budi@sedapmalam.com',
                'owner_password' => 'Password123!',
                'store_name' => 'Outlet Kelapa Gading',
                'store_code' => 'KG-01',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Resto Sedap Malam');

        $this->assertDatabaseHas('tenants', ['email' => 'sedapmalam@resto.com']);
        $this->assertDatabaseHas('users', ['email' => 'budi@sedapmalam.com']);
    }

    public function test_internal_admin_can_update_tenant_status(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/internal/tenants/{$this->tenant->id}/status", [
                'status' => 'SUSPENDED',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'SUSPENDED');

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_public_customer_can_track_order_status(): void
    {
        // 1. Customer places order via QR
        $orderResponse = $this->postJson("/api/public/qr/{$this->qrCode->token}/order", [
            'customer_name' => 'Dewi Safitri',
            'customer_phone' => '08123456789',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'notes' => 'Less sugar please',
                ],
            ],
        ]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        // 2. Customer tracks order status via public endpoint without token
        $trackResponse = $this->getJson("/api/public/qr/{$this->qrCode->token}/order/{$orderId}");

        $trackResponse->assertStatus(200)
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.customer_name', 'Dewi Safitri')
            ->assertJsonPath('data.status', 'PENDING');

        // 3. Resolve QR includes active order
        $resolveResponse = $this->getJson("/api/public/qr/{$this->qrCode->token}");
        $resolveResponse->assertStatus(200)
            ->assertJsonPath('data.active_order.id', $orderId);
    }
}
