<?php

namespace Database\Seeders;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\RecipeMenu\Models\RecipeItem;
use App\Modules\System\Services\SettingService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    /**
     * Seed initial demo coffee shop tenant with complete starter data.
     */
    public function run(): void
    {
        $rbacService = app(RbacService::class);
        $settingService = app(SettingService::class);

        // 1. Create Starter Tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'kopi-nusantara'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Kopi Nusantara',
                'email' => 'owner@kopinusantara.com',
                'phone' => '021-7654321',
                'address' => 'Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Create Starter Store Outlet
        $store = Store::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SNP-01'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Outlet Senopati',
                'phone' => '021-7654321',
                'address' => 'Jl. Senopati No. 45, Jakarta Selatan',
                'timezone' => 'Asia/Jakarta',
                'status' => CommonStatus::ACTIVE,
            ]
        );

        // 3. Create Default Tenant Roles
        $roles = $rbacService->createDefaultTenantRoles($tenant);

        // 4. Create Tenant Users for Testing
        $users = [
            [
                'email' => 'owner@kopinusantara.com',
                'name' => 'Bambang Sudiro (Owner)',
                'phone' => '0812-3456-7890',
                'job_title' => 'Owner & Founder',
                'password' => 'Password123!',
                'role' => $roles['Owner'],
                'store_id' => null, // Tenant-wide
            ],
            [
                'email' => 'manager@kopinusantara.com',
                'name' => 'Dimas Pratama (Manager)',
                'phone' => '0812-3456-7891',
                'job_title' => 'Store & Operations Manager',
                'password' => 'Password123!',
                'role' => $roles['Manager'],
                'store_id' => $store->id,
            ],
            [
                'email' => 'kasir@kopinusantara.com',
                'name' => 'Lestari Putri (Kasir)',
                'phone' => '0812-3456-7892',
                'job_title' => 'Head Cashier',
                'password' => 'Password123!',
                'role' => $roles['Kasir'],
                'store_id' => $store->id,
            ],
            [
                'email' => 'inventory@kopinusantara.com',
                'name' => 'Ahmad Rian (Inventory & Stok)',
                'phone' => '0812-3456-7893',
                'job_title' => 'Staff Logistik & Gudang',
                'password' => 'Password123!',
                'role' => $roles['Inventory'],
                'store_id' => null, // Tenant-wide
            ],
            [
                'email' => 'dapur@kopinusantara.com',
                'name' => 'Chef Junaedi (Dapur & KDS)',
                'phone' => '0812-3456-7894',
                'job_title' => 'Head Kitchen & Barista',
                'password' => 'Password123!',
                'role' => $roles['Dapur'],
                'store_id' => $store->id,
            ],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'phone' => $u['phone'],
                    'job_title' => $u['job_title'],
                    'password' => $u['password'],
                    'is_internal' => false,
                    'is_active' => true,
                ]
            );

            TenantUser::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['id' => (string) Str::ulid(), 'joined_at' => now()]
            );

            // Re-assign role
            \App\Modules\Rbac\Models\UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->delete();

            $rbacService->assignRole($user, $u['role'], $tenant->id, $u['store_id']);
        }

        // Units Reference
        $unitGram = Unit::where('code', 'GR')->first();
        $unitMl = Unit::where('code', 'ML')->first();
        $unitPcs = Unit::where('code', 'PCS')->first();

        if (!$unitGram || !$unitMl || !$unitPcs) {
            return;
        }

        // 5. Create Master Ingredients & Initial Stocks
        $ingredientsData = [
            [
                'name' => 'Biji Kopi Espresso Blend',
                'sku' => 'ING-ESP-01',
                'unit_id' => $unitGram->id,
                'stock' => 5000.0000,
                'avg_cost' => 220.00,
            ],
            [
                'name' => 'Susu Segar Full Cream',
                'sku' => 'ING-MLK-01',
                'unit_id' => $unitMl->id,
                'stock' => 20000.0000,
                'avg_cost' => 25.00,
            ],
            [
                'name' => 'Sirup Vanilla Madagascar',
                'sku' => 'ING-VAN-01',
                'unit_id' => $unitMl->id,
                'stock' => 3000.0000,
                'avg_cost' => 150.00,
            ],
            [
                'name' => 'Paper Cup Hot/Cold 12oz',
                'sku' => 'ING-CUP-12',
                'unit_id' => $unitPcs->id,
                'stock' => 500.0000,
                'avg_cost' => 800.00,
            ],
        ];

        $ingredients = [];
        foreach ($ingredientsData as $ingData) {
            $ingredient = Ingredient::firstOrCreate(
                ['store_id' => $store->id, 'sku' => $ingData['sku']],
                [
                    'id' => (string) Str::ulid(),
                    'unit_id' => $ingData['unit_id'],
                    'name' => $ingData['name'],
                    'status' => CommonStatus::ACTIVE,
                ]
            );

            InventoryStock::firstOrCreate(
                ['store_id' => $store->id, 'ingredient_id' => $ingredient->id],
                [
                    'id' => (string) Str::ulid(),
                    'quantity' => $ingData['stock'],
                    'average_cost' => $ingData['avg_cost'],
                ]
            );

            $ingredients[$ingData['sku']] = $ingredient;
        }

        // 6. Create Master Products & Recipes
        $productsData = [
            [
                'name' => 'Single Espresso',
                'sku' => 'PRD-ESP-01',
                'selling_price' => 20000.00,
                'description' => 'Ekstraksi single shot espresso pekat dengan crema keemasan yang tebal.',
                'image_url' => 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?w=600&auto=format&fit=crop&q=80',
                'recipe' => [
                    ['ing_sku' => 'ING-ESP-01', 'unit_id' => $unitGram->id, 'qty' => 18.0],
                ],
            ],
            [
                'name' => 'Iced Americano',
                'sku' => 'PRD-AME-01',
                'selling_price' => 28000.00,
                'description' => 'Double shot espresso dingin yang segar dengan es batu dan air mineral murni.',
                'image_url' => 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=600&auto=format&fit=crop&q=80',
                'recipe' => [
                    ['ing_sku' => 'ING-ESP-01', 'unit_id' => $unitGram->id, 'qty' => 18.0],
                    ['ing_sku' => 'ING-CUP-12', 'unit_id' => $unitPcs->id, 'qty' => 1.0],
                ],
            ],
            [
                'name' => 'Caffe Latte',
                'sku' => 'PRD-LAT-01',
                'selling_price' => 35000.00,
                'description' => 'Kombinasi harmonis espresso harum dan steamed fresh milk lembut berpola latte art.',
                'image_url' => 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&auto=format&fit=crop&q=80',
                'recipe' => [
                    ['ing_sku' => 'ING-ESP-01', 'unit_id' => $unitGram->id, 'qty' => 18.0],
                    ['ing_sku' => 'ING-MLK-01', 'unit_id' => $unitMl->id, 'qty' => 150.0],
                    ['ing_sku' => 'ING-CUP-12', 'unit_id' => $unitPcs->id, 'qty' => 1.0],
                ],
            ],
            [
                'name' => 'Vanilla Latte',
                'sku' => 'PRD-VLAT-01',
                'selling_price' => 38000.00,
                'description' => 'Latte creamy spesial dipadukan dengan aroma manis sirup vanilla alami Madagascar.',
                'image_url' => 'https://images.unsplash.com/photo-1570968915860-54d5c301fa9f?w=600&auto=format&fit=crop&q=80',
                'recipe' => [
                    ['ing_sku' => 'ING-ESP-01', 'unit_id' => $unitGram->id, 'qty' => 18.0],
                    ['ing_sku' => 'ING-MLK-01', 'unit_id' => $unitMl->id, 'qty' => 150.0],
                    ['ing_sku' => 'ING-VAN-01', 'unit_id' => $unitMl->id, 'qty' => 20.0],
                    ['ing_sku' => 'ING-CUP-12', 'unit_id' => $unitPcs->id, 'qty' => 1.0],
                ],
            ],
            [
                'name' => 'Matcha Green Tea Latte',
                'sku' => 'PRD-MAT-01',
                'selling_price' => 36000.00,
                'description' => 'Bubuk matcha Uji Jepang premium diseduh dengan susu segar yang creamy dan harum.',
                'image_url' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600&auto=format&fit=crop&q=80',
                'recipe' => [
                    ['ing_sku' => 'ING-MLK-01', 'unit_id' => $unitMl->id, 'qty' => 150.0],
                    ['ing_sku' => 'ING-CUP-12', 'unit_id' => $unitPcs->id, 'qty' => 1.0],
                ],
            ],
            [
                'name' => 'Butter Croissant',
                'sku' => 'PRD-CRS-01',
                'selling_price' => 25000.00,
                'description' => 'Pastry Prancis renyah berlapis dengan aroma butter New Zealand yang wangi dan gurih.',
                'image_url' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&auto=format&fit=crop&q=80',
                'recipe' => [],
            ],
        ];

        foreach ($productsData as $prodData) {
            $product = Product::firstOrCreate(
                ['store_id' => $store->id, 'sku' => $prodData['sku']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $prodData['name'],
                    'selling_price' => $prodData['selling_price'],
                    'description' => $prodData['description'],
                    'image_url' => $prodData['image_url'],
                    'status' => CommonStatus::ACTIVE,
                ]
            );

            $product->update([
                'name' => $prodData['name'],
                'selling_price' => $prodData['selling_price'],
                'description' => $prodData['description'],
                'image_url' => $prodData['image_url'],
                'status' => CommonStatus::ACTIVE,
            ]);

            // Create Recipe
            if (!empty($prodData['recipe'])) {
                $recipe = Recipe::firstOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    [
                        'id' => (string) Str::ulid(),
                        'name' => 'Resep Standar ' . $product->name,
                        'status' => CommonStatus::ACTIVE,
                    ]
                );

                foreach ($prodData['recipe'] as $recItem) {
                    if (isset($ingredients[$recItem['ing_sku']])) {
                        RecipeItem::firstOrCreate(
                        [
                            'recipe_id' => $recipe->id,
                            'ingredient_id' => $ingredients[$recItem['ing_sku']]->id,
                        ],
                        [
                            'id' => (string) Str::ulid(),
                            'unit_id' => $recItem['unit_id'],
                            'quantity' => $recItem['qty'],
                        ]
                    );
                }
            }
        }
    }

        // 7. Create Tables with Active QR Codes
        $tablesData = [
            ['name' => 'Meja 01', 'code' => 'M01', 'capacity' => 2],
            ['name' => 'Meja 02', 'code' => 'M02', 'capacity' => 4],
            ['name' => 'Meja 03', 'code' => 'M03', 'capacity' => 4],
            ['name' => 'Meja VIP 01', 'code' => 'VIP1', 'capacity' => 8],
        ];

        foreach ($tablesData as $tData) {
            $table = Table::firstOrCreate(
                ['store_id' => $store->id, 'code' => $tData['code']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $tData['name'],
                    'capacity' => $tData['capacity'],
                    'status' => TableStatus::ACTIVE,
                ]
            );

            // Generate initial QR code if none active
            $hasActiveQr = QrCode::where('table_id', $table->id)
                ->where('status', QrStatus::ACTIVE)
                ->exists();

            if (!$hasActiveQr) {
                QrCode::create([
                    'id' => (string) Str::ulid(),
                    'store_id' => $store->id,
                    'table_id' => $table->id,
                    'token' => bin2hex(random_bytes(32)),
                    'status' => QrStatus::ACTIVE,
                ]);
            }
        }

        // 8. Default Store Settings
        $settingService->set('currency', 'IDR', $tenant->id, null);
        $settingService->set('tax_percentage', '11.0', $tenant->id, null);
        $settingService->set('service_charge_percentage', '5.0', $tenant->id, null);
        $settingService->set(
            'receipt_footer',
            'Terima kasih atas kunjungan Anda di Kopi Nusantara Senopati!',
            $tenant->id,
            $store->id
        );
    }
}
