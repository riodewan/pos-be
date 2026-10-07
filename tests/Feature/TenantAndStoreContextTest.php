<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\RoleScope;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAndStoreContextTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Store $storeA1;
    protected Store $storeA2;
    protected Store $storeB1;
    protected User $userA;
    protected User $userB;
    protected User $internalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any leftover context
        AppContext::clear();

        // Register routes for testing context middleware
        Route::middleware(['auth:sanctum', 'tenant.context'])->prefix('test-tenant')->group(function () {
            Route::get('/info', function () {
                return response()->json([
                    'tenant_id' => AppContext::tenantId(),
                ]);
            });
        });

        Route::middleware(['auth:sanctum', 'tenant.context', 'store.context'])->prefix('test-store')->group(function () {
            Route::get('/info', function () {
                return response()->json([
                    'tenant_id' => AppContext::tenantId(),
                    'store_id' => AppContext::storeId(),
                ]);
            });

            Route::get('/products', function () {
                return response()->json([
                    'products' => Product::all(),
                ]);
            });
        });

        // Seed test data
        $this->tenantA = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha-' . Str::random(5),
            'email' => 'alpha_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta-' . Str::random(5),
            'email' => 'beta_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        $this->storeA1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Alpha One',
            'code' => 'SA1-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->storeA2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Alpha Two',
            'code' => 'SA2-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->storeB1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Store Beta One',
            'code' => 'SB1-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->userA = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'User Alpha',
            'email' => 'user_alpha_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
        ]);

        $this->userB = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'User Beta',
            'email' => 'user_beta_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
        ]);

        $this->internalUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Internal Super Admin',
            'email' => 'admin_' . Str::random(5) . '@platform.com',
            'password' => 'secret123',
            'is_internal' => true,
            'is_active' => true,
        ]);
    }

    public function test_tenant_context_requires_header(): void
    {
        $response = $this->actingAs($this->userA)->getJson('/test-tenant/info');

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_context_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', (string) Str::ulid())
            ->getJson('/test-tenant/info');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_context_forbids_unauthorized_user(): void
    {
        // User B tries to access Tenant A
        $response = $this->actingAs($this->userB)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson('/test-tenant/info');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_context_allows_member_user(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson('/test-tenant/info');

        $response->assertStatus(200)
            ->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_tenant_context_allows_internal_admin(): void
    {
        $response = $this->actingAs($this->internalUser)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson('/test-tenant/info');

        $response->assertStatus(200)
            ->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_store_context_requires_header(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson('/test-store/info');

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_store_context_rejects_store_belonging_to_another_tenant(): void
    {
        // Store B1 belongs to Tenant B, requested within Tenant A
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeB1->id)
            ->getJson('/test-store/info');

        $response->assertStatus(404);
    }

    public function test_store_context_resolves_correctly_for_valid_store(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-store/info');

        $response->assertStatus(200)
            ->assertJsonPath('tenant_id', $this->tenantA->id)
            ->assertJsonPath('store_id', $this->storeA1->id);
    }

    public function test_store_context_forbids_user_restricted_to_another_store(): void
    {
        // Create role in Tenant A
        $role = Role::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier Store 2 Only',
            'scope' => RoleScope::TENANT,
        ]);

        // Assign userA specifically to Store A2
        UserRole::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->userA->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA2->id,
        ]);

        // userA attempts to access Store A1 -> forbidden!
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-store/info');

        $response->assertStatus(403);

        // userA attempts to access Store A2 -> allowed!
        $response2 = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA2->id)
            ->getJson('/test-store/info');

        $response2->assertStatus(200)
            ->assertJsonPath('store_id', $this->storeA2->id);
    }

    public function test_product_query_is_scoped_to_active_store_and_prevents_leakage(): void
    {
        // Create product in Store A1
        Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->storeA1->id,
            'name' => 'Kopi Espresso A1',
            'sku' => 'SKU-A1-' . Str::random(4),
            'selling_price' => 25000,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Create product in Store A2
        Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->storeA2->id,
            'name' => 'Kopi Latte A2',
            'sku' => 'SKU-A2-' . Str::random(4),
            'selling_price' => 30000,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Request Store A1 products
        $responseA1 = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-store/products');

        $responseA1->assertStatus(200);
        $productsA1 = $responseA1->json('products');
        $this->assertCount(1, $productsA1);
        $this->assertEquals('Kopi Espresso A1', $productsA1[0]['name']);

        // Request Store A2 products
        $responseA2 = $this->actingAs($this->userA)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA2->id)
            ->getJson('/test-store/products');

        $responseA2->assertStatus(200);
        $productsA2 = $responseA2->json('products');
        $this->assertCount(1, $productsA2);
        $this->assertEquals('Kopi Latte A2', $productsA2[0]['name']);
    }
}
