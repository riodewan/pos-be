<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $owner;
    protected User $cashier;
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
            'name' => 'Kopi Rasa Sejati',
            'slug' => 'kopi-rasa-sejati-' . Str::random(4),
            'email' => 'krs_' . Str::random(5) . '@tenant.com',
            'phone' => '08123456789',
            'address' => 'Jl. Sudirman No. 10',
            'status' => 'ACTIVE',
        ]);

        // Create initial stores
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Senopati',
            'code' => 'OUT-SNP-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->store2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Kemang',
            'code' => 'OUT-KMG-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Seed roles for Tenant
        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Create Owner
        $this->owner = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Pak Bos Owner',
            'email' => 'bos_' . Str::random(5) . '@tenant.com',
            'password' => 'secret123',
            'is_internal' => false,
            'is_active' => true,
        ]);

        TenantUser::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
        ]);

        $this->rbacService->assignRole($this->owner, $this->roles['Owner'], $this->tenant->id, null);

        // Create Cashier
        $this->cashier = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Mbak Kasir',
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
    }

    public function test_get_tenant_profile(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/tenant/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Kopi Rasa Sejati')
            ->assertJsonPath('data.stores_count', 2)
            ->assertJsonPath('data.users_count', 2);
    }

    public function test_update_tenant_profile(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson('/api/tenant/profile', [
                'name' => 'Kopi Rasa Sejati Indonesia',
                'phone' => '0899999999',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Kopi Rasa Sejati Indonesia')
            ->assertJsonPath('data.phone', '0899999999');
    }

    public function test_list_stores(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/tenant/stores');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_create_store(): void
    {
        $storeCode = 'OUT-BGD-' . Str::random(3);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/tenant/stores', [
                'name' => 'Outlet Bandung',
                'code' => $storeCode,
                'address' => 'Jl. Riau No. 5',
                'timezone' => 'Asia/Jakarta',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Outlet Bandung')
            ->assertJsonPath('data.code', $storeCode);

        // Attempt creating store with duplicate code in the same tenant -> 422
        $dupResponse = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/tenant/stores', [
                'name' => 'Outlet Bandung Dua',
                'code' => $storeCode,
            ]);

        $dupResponse->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_update_store(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson("/api/tenant/stores/{$this->store1->id}", [
                'name' => 'Outlet Senopati Updated',
                'phone' => '021-555555',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Outlet Senopati Updated');
    }

    public function test_delete_store_prevents_deleting_last_store(): void
    {
        // 1. Delete store2 (tenant now has 1 store left)
        $response1 = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/tenant/stores/{$this->store2->id}");

        $response1->assertStatus(200);

        // 2. Attempt deleting store1 (the only store remaining) -> 422 Unprocessable
        $response2 = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/tenant/stores/{$this->store1->id}");

        $response2->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_list_tenant_users(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/tenant/users');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_add_new_team_member(): void
    {
        $newEmail = 'barista_' . Str::random(5) . '@tenant.com';

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/tenant/users', [
                'email' => $newEmail,
                'name' => 'Barista Baru',
                'role_id' => $this->roles['Cashier']->id,
                'store_id' => $this->store1->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', $newEmail);
    }

    public function test_update_team_member_roles(): void
    {
        // Promote cashier to Manager
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->putJson("/api/tenant/users/{$this->cashier->id}/roles", [
                'roles' => [
                    [
                        'role_id' => $this->roles['Manager']->id,
                        'store_id' => null, // Tenant-wide manager
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // Verify Cashier now has Manager role
        $this->assertTrue($this->cashier->hasRole('Manager', $this->tenant->id));
    }

    public function test_remove_team_member_protects_only_owner(): void
    {
        // 1. Attempt removing sole Owner -> 422 Unprocessable
        $respOwner = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/tenant/users/{$this->owner->id}");

        $respOwner->assertStatus(422);

        // 2. Removing cashier -> 200 OK
        $respCashier = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->deleteJson("/api/tenant/users/{$this->cashier->id}");

        $respCashier->assertStatus(200);
    }

    public function test_list_tenant_roles(): void
    {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/tenant/roles');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }
}
