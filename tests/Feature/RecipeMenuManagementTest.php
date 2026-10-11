<?php

namespace Tests\Feature;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\UnitType;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\RecipeMenu\Models\Menu;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecipeMenuManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected RbacService $rbacService;
    protected Tenant $tenant;
    protected Store $store1;
    protected Store $store2;
    protected User $manager;
    protected Product $productStore1;
    protected Product $productStore2;
    protected Ingredient $ingredientStore1;
    protected Ingredient $ingredientStore2;
    protected Unit $unitGram;
    protected Unit $unitMl;
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
            'name' => 'Kopi Sejiwa Cerita',
            'slug' => 'kopi-sejiwa-' . Str::random(4),
            'email' => 'ksc_' . Str::random(5) . '@tenant.com',
            'status' => 'ACTIVE',
        ]);

        // Create Stores
        $this->store1 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Bandung',
            'code' => 'BDG-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->store2 = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Outlet Jakarta',
            'code' => 'JKT-' . Str::random(3),
            'timezone' => 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->roles = $this->rbacService->createDefaultTenantRoles($this->tenant);

        // Create Manager (tenant-wide)
        $this->manager = User::create([
            'id' => (string) Str::ulid(),
            'name' => 'Chef Barista Manager',
            'email' => 'chef_' . Str::random(5) . '@tenant.com',
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

        // Seed Units
        $this->unitGram = Unit::firstOrCreate(
            ['code' => 'GR'],
            ['id' => (string) Str::ulid(), 'name' => 'Gram', 'type' => UnitType::WEIGHT]
        );

        $this->unitMl = Unit::firstOrCreate(
            ['code' => 'ML'],
            ['id' => (string) Str::ulid(), 'name' => 'Milliliter', 'type' => UnitType::VOLUME]
        );

        // Store 1 Catalog
        $this->productStore1 = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Cappuccino Special',
            'sku' => 'CAP-001',
            'selling_price' => 35000,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->ingredientStore1 = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'unit_id' => $this->unitGram->id,
            'name' => 'Espresso Beans S1',
            'sku' => 'ING-ESP-S1',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Store 2 Catalog
        $this->productStore2 = Product::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'name' => 'Jakarta Special Latte',
            'sku' => 'LAT-002',
            'selling_price' => 40000,
            'status' => CommonStatus::ACTIVE,
        ]);

        $this->ingredientStore2 = Ingredient::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'unit_id' => $this->unitMl->id,
            'name' => 'Fresh Milk S2',
            'sku' => 'ING-MLK-S2',
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    public function test_create_recipe_with_items_and_store_scoping(): void
    {
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/recipes', [
                'product_id' => $this->productStore1->id,
                'name' => 'Standard Recipe Cappuccino',
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientStore1->id,
                        'unit_id' => $this->unitGram->id,
                        'quantity' => 18.5,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Standard Recipe Cappuccino')
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonPath('data.items.0.quantity', 18.5);

        $this->assertDatabaseHas('recipes', [
            'store_id' => $this->store1->id,
            'product_id' => $this->productStore1->id,
        ]);
    }

    public function test_create_recipe_rejects_cross_store_product(): void
    {
        // Attempt creating recipe in Store 1 using Product from Store 2 -> 422
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/recipes', [
                'product_id' => $this->productStore2->id,
                'name' => 'Invalid Cross Store Recipe',
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientStore1->id,
                        'unit_id' => $this->unitGram->id,
                        'quantity' => 15,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The selected product does not belong to the current store.');
    }

    public function test_create_recipe_rejects_cross_store_ingredient(): void
    {
        // Attempt creating recipe in Store 1 using Ingredient from Store 2 -> 422
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/recipes', [
                'product_id' => $this->productStore1->id,
                'name' => 'Invalid Cross Store Ingredient',
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientStore2->id, // Belongs to Store 2!
                        'unit_id' => $this->unitMl->id,
                        'quantity' => 100,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'One or more selected ingredients do not belong to the current store.');
    }

    public function test_update_recipe_and_replace_items(): void
    {
        // Create initial recipe
        $recipe = Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'product_id' => $this->productStore1->id,
            'name' => 'Old Cappuccino Recipe',
            'status' => CommonStatus::ACTIVE,
        ]);

        $recipe->recipeItems()->create([
            'id' => (string) Str::ulid(),
            'ingredient_id' => $this->ingredientStore1->id,
            'unit_id' => $this->unitGram->id,
            'quantity' => 15,
        ]);

        // Update
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->putJson("/api/recipes/{$recipe->id}", [
                'name' => 'Updated Double Shot Recipe',
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientStore1->id,
                        'unit_id' => $this->unitGram->id,
                        'quantity' => 22,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Double Shot Recipe')
            ->assertJsonPath('data.items.0.quantity', 22);
    }

    public function test_delete_recipe(): void
    {
        $recipe = Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'product_id' => $this->productStore1->id,
            'name' => 'To Be Deleted Recipe',
            'status' => CommonStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->deleteJson("/api/recipes/{$recipe->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    }

    public function test_create_menu_with_products(): void
    {
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/menus', [
                'name' => 'Menu Sarapan Pagi',
                'description' => 'Menu kopi dan roti pagi',
                'product_ids' => [$this->productStore1->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Menu Sarapan Pagi')
            ->assertJsonPath('data.store_id', $this->store1->id)
            ->assertJsonPath('data.products.0.id', $this->productStore1->id);
    }

    public function test_create_menu_rejects_cross_store_product(): void
    {
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->postJson('/api/menus', [
                'name' => 'Invalid Menu',
                'product_ids' => [$this->productStore2->id], // Product belongs to Store 2!
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'One or more selected products do not belong to the current store.');
    }

    public function test_update_and_delete_menu(): void
    {
        $menu = Menu::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'name' => 'Menu Sore',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Update
        $updateResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->putJson("/api/menus/{$menu->id}", [
                'name' => 'Menu Senja Romantis',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'Menu Senja Romantis');

        // Delete
        $delResp = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->deleteJson("/api/menus/{$menu->id}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
    }

    public function test_cross_store_recipe_and_menu_isolation(): void
    {
        // Recipe in Store 1
        Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store1->id,
            'product_id' => $this->productStore1->id,
            'name' => 'Recipe Store 1',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Recipe in Store 2
        Recipe::create([
            'id' => (string) Str::ulid(),
            'store_id' => $this->store2->id,
            'product_id' => $this->productStore2->id,
            'name' => 'Recipe Store 2',
            'status' => CommonStatus::ACTIVE,
        ]);

        // Query Store 1 recipes
        $respStore1 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store1->id)
            ->getJson('/api/recipes');

        $respStore1->assertStatus(200);
        $itemsStore1 = $respStore1->json('data');
        $this->assertCount(1, $itemsStore1);
        $this->assertEquals('Recipe Store 1', $itemsStore1[0]['name']);

        // Query Store 2 recipes
        $respStore2 = $this->actingAs($this->manager)
            ->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeader('X-Store-Id', $this->store2->id)
            ->getJson('/api/recipes');

        $respStore2->assertStatus(200);
        $itemsStore2 = $respStore2->json('data');
        $this->assertCount(1, $itemsStore2);
        $this->assertEquals('Recipe Store 2', $itemsStore2[0]['name']);
    }
}
