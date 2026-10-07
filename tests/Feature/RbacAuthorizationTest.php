<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Store $storeA1;
    protected Store $storeA2;
    protected Store $storeB1;
    protected array $rolesA;

    protected function setUp(): void
    {
        parent::setUp();

        AppContext::clear();
        $this->rbacService = app(RbacService::class);

        // Run RbacSeeder to ensure permissions are present
        $this->seed(RbacSeeder::class);

        // Setup test routes for middleware testing
        Route::middleware(['auth:sanctum', 'tenant.context', 'store.context', 'permission:inventory.adjust'])
            ->get('/test-rbac/inventory-adjust', function () {
                return response()->json(['success' => true, 'action' => 'inventory-adjusted']);
            });

        Route::middleware(['auth:sanctum', 'tenant.context', 'store.context', 'permission:order.create'])
            ->get('/test-rbac/create-order', function () {
                return response()->json(['success' => true, 'action' => 'order-created']);
            });

        Route::middleware(['auth:sanctum', 'tenant.context', 'role:Owner,Manager'])
            ->get('/test-rbac/owner-only', function () {
                return response()->json(['success' => true, 'action' => 'management-action']);
            });

        // Create Tenants
        $this->tenantA = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kopi Sejahtera',
            'slug' => 'kopi-sejahtera-' . Str::random(4),
            'email' => 'ks_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::ulid(),
            'name' => 'Kopi Maju',
            'slug' => 'kopi-maju-' . Str::random(4),
            'email' => 'km_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Stores
        $this->storeA1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Jakarta',
            'code' => 'JKT-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->storeA2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Bandung',
            'code' => 'BDG-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->storeB1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Store Surabaya',
            'code' => 'SBY-' . Str::random(4),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Seed default roles for Tenant A
        $this->rolesA = $this->rbacService->createDefaultTenantRoles($this->tenantA);
    }

    public function test_default_tenant_roles_created_with_appropriate_permissions(): void
    {
        $this->assertArrayHasKey('Owner', $this->rolesA);
        $this->assertArrayHasKey('Manager', $this->rolesA);
        $this->assertArrayHasKey('Inventory', $this->rolesA);
        $this->assertArrayHasKey('Cashier', $this->rolesA);

        $cashierRole = $this->rolesA['Cashier'];
        $cashierPermissions = $cashierRole->permissions->pluck('name')->all();

        $this->assertContains('order.create', $cashierPermissions);
        $this->assertContains('payment.process', $cashierPermissions);
        $this->assertNotContains('tenant.delete', $cashierPermissions);
        $this->assertNotContains('inventory.adjust', $cashierPermissions);

        $inventoryRole = $this->rolesA['Inventory'];
        $inventoryPermissions = $inventoryRole->permissions->pluck('name')->all();
        $this->assertContains('inventory.adjust', $inventoryPermissions);
        $this->assertNotContains('payment.process', $inventoryPermissions);
    }

    public function test_assign_role_validates_cross_tenant_and_cross_store_boundaries(): void
    {
        $user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'John Doe',
            'email' => 'john_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        // 1. Assigning Role from Tenant A to Tenant B throws exception
        $this->expectException(InvalidArgumentException::class);
        $this->rbacService->assignRole($user, $this->rolesA['Cashier'], $this->tenantB->id);
    }

    public function test_assign_role_rejects_store_belonging_to_another_tenant(): void
    {
        $user = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Jane Doe',
            'email' => 'jane_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        // Store B1 belongs to Tenant B, but we pass tenantA
        $this->expectException(InvalidArgumentException::class);
        $this->rbacService->assignRole($user, $this->rolesA['Cashier'], $this->tenantA->id, $this->storeB1->id);
    }

    public function test_user_has_permission_respects_store_scope(): void
    {
        $cashierUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Cashier Outlet 1',
            'email' => 'cashier_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $cashierUser->id,
        ]);

        // Assign Cashier role scoped specifically to Store A1
        $this->rbacService->assignRole($cashierUser, $this->rolesA['Cashier'], $this->tenantA->id, $this->storeA1->id);

        // When evaluating Store A1
        $this->assertTrue($cashierUser->hasPermission('order.create', $this->tenantA->id, $this->storeA1->id));
        $this->assertFalse($cashierUser->hasPermission('inventory.adjust', $this->tenantA->id, $this->storeA1->id));

        // When evaluating Store A2 (cashier does not have role at Store A2)
        $this->assertFalse($cashierUser->hasPermission('order.create', $this->tenantA->id, $this->storeA2->id));
    }

    public function test_tenant_wide_role_applies_across_all_stores(): void
    {
        $managerUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'General Manager',
            'email' => 'gm_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $managerUser->id,
        ]);

        // Assign Manager role tenant-wide (store_id = null)
        $this->rbacService->assignRole($managerUser, $this->rolesA['Manager'], $this->tenantA->id, null);

        // Applies in Store A1
        $this->assertTrue($managerUser->hasPermission('product.create', $this->tenantA->id, $this->storeA1->id));
        // Applies in Store A2
        $this->assertTrue($managerUser->hasPermission('product.create', $this->tenantA->id, $this->storeA2->id));
    }

    public function test_internal_admin_has_all_permissions(): void
    {
        $internalAdmin = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Internal Master Admin',
            'email' => 'master_' . Str::random(5) . '@platform.com',
            'password' => 'secret123',
            'is_internal' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($internalAdmin->hasPermission('any.random.permission'));
        $this->assertTrue($internalAdmin->hasPermission('tenant.delete'));
        $this->assertTrue(Gate::forUser($internalAdmin)->allows('order.create'));
    }

    public function test_permission_middleware_enforcement(): void
    {
        $cashierUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Store Cashier',
            'email' => 'cashier_mid_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $cashierUser->id,
        ]);

        $this->rbacService->assignRole($cashierUser, $this->rolesA['Cashier'], $this->tenantA->id, $this->storeA1->id);

        // 1. Cashier accessing order.create -> 200 OK
        $response1 = $this->actingAs($cashierUser)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-rbac/create-order');

        $response1->assertStatus(200)
            ->assertJsonPath('action', 'order-created');

        // 2. Cashier accessing inventory.adjust -> 403 Forbidden
        $response2 = $this->actingAs($cashierUser)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-rbac/inventory-adjust');

        $response2->assertStatus(403);
    }

    public function test_role_middleware_enforcement(): void
    {
        $cashierUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Cashier Only',
            'email' => 'cashier_rolemid_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $cashierUser->id,
        ]);

        $this->rbacService->assignRole($cashierUser, $this->rolesA['Cashier'], $this->tenantA->id, $this->storeA1->id);

        // Cashier tries to access Owner/Manager only route -> 403 Forbidden
        $response = $this->actingAs($cashierUser)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-rbac/owner-only');

        $response->assertStatus(403);

        // Owner tries to access -> 200 OK
        $ownerUser = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Store Owner',
            'email' => 'owner_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $ownerUser->id,
        ]);

        $this->rbacService->assignRole($ownerUser, $this->rolesA['Owner'], $this->tenantA->id, null);

        $responseOwner = $this->actingAs($ownerUser)
            ->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->withHeader('X-Store-Id', $this->storeA1->id)
            ->getJson('/test-rbac/owner-only');

        $responseOwner->assertStatus(200)
            ->assertJsonPath('action', 'management-action');
    }
}
