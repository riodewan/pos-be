<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\UnitType;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $manager;
    protected Unit $unitKg;
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
            'name' => 'Kopi Aroma Senja',
            'slug' => 'kopi-aroma-senja-' . Str::random(4),
            'email' => 'kas_' . Str::random(5) . '@tenant.com',
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
            'name' => 'Outlet Blok M',
            'code' => 'BLM-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Create Manager (tenant-wide)
        $this->manager = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Manager Toko',
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

        // Seed a standard unit
        $this->unitKg = Unit::firstOrCreate(
            ['code' => 'KG'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Kilogram',
                'type' => UnitType::WEIGHT,
            ]
        );
    }

    public function test_list_and_create_units(): void
    {
        // 1. List units
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->getJson('/api/catalog/units');

        $response->assertStatus(200);

        // 2. Create unit
        $unitCode = 'BOX-' . Str::random(3);
        $createResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/units', [
                'name' => 'Box Container ' . $unitCode,
                'code' => $unitCode,
                'type' => 'COUNT',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.code', $unitCode);
    }

    public function test_create_product_with_automatic_store_scoping(): void
    {
        $sku = 'PRD-' . Str::random(4);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/products', [
                'name' => 'Caramel Macchiato',
                'sku' => $sku,
                'description' => 'Espresso with vanilla syrup and caramel drizzle',
                'selling_price' => 38000,
                'status' => 'ACTIVE',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Caramel Macchiato')
            ->assertJsonPath('data.selling_price', 38000)
            ->assertJsonPath('data.store_id', $this->store1->id);

        // Verify database row has store_id matching store1
        $this->assertDatabaseHas('products', [
            'store_id' => $this->store1->id,
            'sku' => $sku,
        ]);
    }

    public function test_unique_sku_enforced_per_store_but_allowed_across_different_stores(): void
    {
        $sharedSku = 'COFFEE-ESPRESSO';

        // 1. Create product in Store 1
        $resp1 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/products', [
                'name' => 'Espresso Single',
                'sku' => $sharedSku,
                'selling_price' => 20000,
            ]);
        $resp1->assertStatus(201);

        // 2. Duplicate SKU in Store 1 -> 422
        $respDuplicate = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/products', [
                'name' => 'Espresso Double',
                'sku' => $sharedSku,
                'selling_price' => 25000,
            ]);
        $respDuplicate->assertStatus(422)
            ->assertJsonValidationErrors('sku');

        // 3. Same SKU in Store 2 -> Allowed! (Per-store SKU uniqueness)
        $respStore2 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store2->id)
            ->postJson('/api/catalog/products', [
                'name' => 'Espresso Single Outlet 2',
                'sku' => $sharedSku,
                'selling_price' => 22000,
            ]);
        $respStore2->assertStatus(201);
    }

    public function test_product_update_and_delete(): void
    {
        $product = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Americano',
            'sku' => 'AME-' . Str::random(4),
            'selling_price' => 28000,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Update
        $updateResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->putJson("/api/catalog/products/{$product->id}", [
                'name' => 'Iced Americano',
                'selling_price' => 30000,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'Iced Americano')
            ->assertJsonPath('data.selling_price', 30000);

        // Delete (Soft delete)
        $delResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->deleteJson("/api/catalog/products/{$product->id}");

        $delResp->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_create_and_list_ingredients_with_unit_relationship(): void
    {
        $ingSku = 'ING-BEANS-' . Str::random(3);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/ingredients', [
                'unit_id' => $this->unitKg->id,
                'name' => 'Biji Kopi Arabica Gayo',
                'sku' => $ingSku,
                'status' => 'ACTIVE',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Biji Kopi Arabica Gayo')
            ->assertJsonPath('data.unit.code', 'KG')
            ->assertJsonPath('data.store_id', $this->store1->id);

        // List ingredients in Store 1
        $listResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->getJson('/api/catalog/ingredients');

        $listResp->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_supplier_crud_operations(): void
    {
        $code = 'SUP-' . Str::random(4);

        // 1. Create Supplier
        $createResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/catalog/suppliers', [
                'name' => 'PT Roastery Nusantara',
                'code' => $code,
                'contact_person' => 'Budi Santoso',
                'phone' => '081234567890',
                'email' => 'sales@roastery.id',
                'address' => 'Gudang Kopi No. 12',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.name', 'PT Roastery Nusantara')
            ->assertJsonPath('data.store_id', $this->store1->id);

        $supplierId = $createResp->json('data.id');

        // 2. Update Supplier
        $updateResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->putJson("/api/catalog/suppliers/{$supplierId}", [
                'phone' => '089999888777',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.phone', '089999888777');

        // 3. Delete Supplier
        $delResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->deleteJson("/api/catalog/suppliers/{$supplierId}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('suppliers', ['id' => $supplierId]);
    }

    public function test_cross_store_catalog_isolation(): void
    {
        // Store 1 Product
        Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Store 1 Secret Drink',
            'sku' => 'S1-SEC',
            'selling_price' => 50000,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Store 2 Product
        Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'name' => 'Store 2 Signature Cake',
            'sku' => 'S2-CAKE',
            'selling_price' => 45000,
            'status' => CommonStatus::ACTIVE,
        ]);

        // Query Store 1 products
        $respStore1 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->getJson('/api/catalog/products');

        $respStore1->assertStatus(200);
        $itemsStore1 = $respStore1->json('data');
        $this->assertCount(1, $itemsStore1);
        $this->assertEquals('Store 1 Secret Drink', $itemsStore1[0]['name']);

        // Query Store 2 products
        $respStore2 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store2->id)
            ->getJson('/api/catalog/products');

        $respStore2->assertStatus(200);
        $itemsStore2 = $respStore2->json('data');
        $this->assertCount(1, $itemsStore2);
        $this->assertEquals('Store 2 Signature Cake', $itemsStore2[0]['name']);
    }
}
