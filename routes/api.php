<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    });
});

/*
|--------------------------------------------------------------------------
| Tenant Management Routes
|--------------------------------------------------------------------------
|
| Operates under active Tenant Context ('X-Tenant-Id' header).
| Protected by RBAC permissions and Sanctum token authentication.
|
*/
Route::middleware(['auth:sanctum', 'tenant.context'])->prefix('tenant')->group(function () {
    // Tenant Profile
    Route::get('/profile', [\App\Modules\Tenant\Http\Controllers\TenantProfileController::class, 'show'])
        ->middleware('permission:tenant.view');
    Route::put('/profile', [\App\Modules\Tenant\Http\Controllers\TenantProfileController::class, 'update'])
        ->middleware('permission:tenant.update');

    // Store Outlets
    Route::get('/stores', [\App\Modules\Tenant\Http\Controllers\StoreController::class, 'index'])
        ->middleware('permission:store.view');
    Route::post('/stores', [\App\Modules\Tenant\Http\Controllers\StoreController::class, 'store'])
        ->middleware('permission:store.create');
    Route::get('/stores/{id}', [\App\Modules\Tenant\Http\Controllers\StoreController::class, 'show'])
        ->middleware('permission:store.view');
    Route::put('/stores/{id}', [\App\Modules\Tenant\Http\Controllers\StoreController::class, 'update'])
        ->middleware('permission:store.update');
    Route::delete('/stores/{id}', [\App\Modules\Tenant\Http\Controllers\StoreController::class, 'destroy'])
        ->middleware('permission:store.delete');

    // Team Members & Roles
    Route::get('/users', [\App\Modules\Tenant\Http\Controllers\TenantUserController::class, 'index'])
        ->middleware('permission:user.view');
    Route::post('/users', [\App\Modules\Tenant\Http\Controllers\TenantUserController::class, 'store'])
        ->middleware('permission:user.invite');
    Route::put('/users/{userId}/roles', [\App\Modules\Tenant\Http\Controllers\TenantUserController::class, 'updateRoles'])
        ->middleware('permission:user.assign_role');
    Route::delete('/users/{userId}', [\App\Modules\Tenant\Http\Controllers\TenantUserController::class, 'destroy'])
        ->middleware('permission:user.delete');

    // Tenant Roles
    Route::get('/roles', [\App\Modules\Tenant\Http\Controllers\TenantRoleController::class, 'index'])
        ->middleware('permission:role.view');
});

/*
|--------------------------------------------------------------------------
| Store-Scoped Catalog & Raw Materials Routes
|--------------------------------------------------------------------------
|
| Operates under active Store Context ('X-Store-Id' / 'X-Store-Code').
| All data automatically scoped to current store.
|
*/
Route::middleware(['auth:sanctum', 'tenant.context', 'store.context'])->prefix('catalog')->group(function () {
    // Measurement Units (Master data)
    Route::get('/units', [\App\Modules\Catalog\Http\Controllers\UnitController::class, 'index'])
        ->middleware('permission:unit.view');
    Route::post('/units', [\App\Modules\Catalog\Http\Controllers\UnitController::class, 'store'])
        ->middleware('permission:unit.create');

    // Products
    Route::get('/products', [\App\Modules\Catalog\Http\Controllers\ProductController::class, 'index'])
        ->middleware('permission:product.view');
    Route::post('/products', [\App\Modules\Catalog\Http\Controllers\ProductController::class, 'store'])
        ->middleware('permission:product.create');
    Route::get('/products/{id}', [\App\Modules\Catalog\Http\Controllers\ProductController::class, 'show'])
        ->middleware('permission:product.view');
    Route::put('/products/{id}', [\App\Modules\Catalog\Http\Controllers\ProductController::class, 'update'])
        ->middleware('permission:product.update');
    Route::delete('/products/{id}', [\App\Modules\Catalog\Http\Controllers\ProductController::class, 'destroy'])
        ->middleware('permission:product.delete');

    // Ingredients
    Route::get('/ingredients', [\App\Modules\Catalog\Http\Controllers\IngredientController::class, 'index'])
        ->middleware('permission:ingredient.view');
    Route::post('/ingredients', [\App\Modules\Catalog\Http\Controllers\IngredientController::class, 'store'])
        ->middleware('permission:ingredient.create');
    Route::get('/ingredients/{id}', [\App\Modules\Catalog\Http\Controllers\IngredientController::class, 'show'])
        ->middleware('permission:ingredient.view');
    Route::put('/ingredients/{id}', [\App\Modules\Catalog\Http\Controllers\IngredientController::class, 'update'])
        ->middleware('permission:ingredient.update');
    Route::delete('/ingredients/{id}', [\App\Modules\Catalog\Http\Controllers\IngredientController::class, 'destroy'])
        ->middleware('permission:ingredient.delete');

    // Suppliers
    Route::get('/suppliers', [\App\Modules\Catalog\Http\Controllers\SupplierController::class, 'index'])
        ->middleware('permission:supplier.view');
    Route::post('/suppliers', [\App\Modules\Catalog\Http\Controllers\SupplierController::class, 'store'])
        ->middleware('permission:supplier.create');
    Route::get('/suppliers/{id}', [\App\Modules\Catalog\Http\Controllers\SupplierController::class, 'show'])
        ->middleware('permission:supplier.view');
    Route::put('/suppliers/{id}', [\App\Modules\Catalog\Http\Controllers\SupplierController::class, 'update'])
        ->middleware('permission:supplier.update');
    Route::delete('/suppliers/{id}', [\App\Modules\Catalog\Http\Controllers\SupplierController::class, 'destroy'])
        ->middleware('permission:supplier.delete');
});

/*
|--------------------------------------------------------------------------
| Store-Scoped Recipes & Menus Routes
|--------------------------------------------------------------------------
|
| Operates under active Store Context ('X-Store-Id' / 'X-Store-Code').
|
*/
Route::middleware(['auth:sanctum', 'tenant.context', 'store.context'])->group(function () {
    // Recipes
    Route::prefix('recipes')->group(function () {
        Route::get('/', [\App\Modules\RecipeMenu\Http\Controllers\RecipeController::class, 'index'])
            ->middleware('permission:recipe.view');
        Route::post('/', [\App\Modules\RecipeMenu\Http\Controllers\RecipeController::class, 'store'])
            ->middleware('permission:recipe.create');
        Route::get('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\RecipeController::class, 'show'])
            ->middleware('permission:recipe.view');
        Route::put('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\RecipeController::class, 'update'])
            ->middleware('permission:recipe.update');
        Route::delete('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\RecipeController::class, 'destroy'])
            ->middleware('permission:recipe.delete');
    });

    // Menus
    Route::prefix('menus')->group(function () {
        Route::get('/', [\App\Modules\RecipeMenu\Http\Controllers\MenuController::class, 'index'])
            ->middleware('permission:menu.view');
        Route::post('/', [\App\Modules\RecipeMenu\Http\Controllers\MenuController::class, 'store'])
            ->middleware('permission:menu.create');
        Route::get('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\MenuController::class, 'show'])
            ->middleware('permission:menu.view');
        Route::put('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\MenuController::class, 'update'])
            ->middleware('permission:menu.update');
        Route::delete('/{id}', [\App\Modules\RecipeMenu\Http\Controllers\MenuController::class, 'destroy'])
            ->middleware('permission:menu.delete');
    });

    // Inventory Management
    Route::prefix('inventory')->group(function () {
        // Purchases
        Route::get('/purchases', [\App\Modules\Inventory\Http\Controllers\PurchaseController::class, 'index'])
            ->middleware('permission:purchase.view');
        Route::post('/purchases', [\App\Modules\Inventory\Http\Controllers\PurchaseController::class, 'store'])
            ->middleware('permission:purchase.create');
        Route::get('/purchases/{id}', [\App\Modules\Inventory\Http\Controllers\PurchaseController::class, 'show'])
            ->middleware('permission:purchase.view');
        Route::post('/purchases/{id}/receive', [\App\Modules\Inventory\Http\Controllers\PurchaseController::class, 'receive'])
            ->middleware('permission:purchase.receive');
        Route::post('/purchases/{id}/cancel', [\App\Modules\Inventory\Http\Controllers\PurchaseController::class, 'cancel'])
            ->middleware('permission:purchase.cancel');

        // Stocks & Adjustments
        Route::get('/stocks', [\App\Modules\Inventory\Http\Controllers\InventoryStockController::class, 'index'])
            ->middleware('permission:inventory.view');
        Route::get('/stocks/{ingredientId}', [\App\Modules\Inventory\Http\Controllers\InventoryStockController::class, 'show'])
            ->middleware('permission:inventory.view');
        Route::post('/adjustments', [\App\Modules\Inventory\Http\Controllers\InventoryStockController::class, 'adjust'])
            ->middleware('permission:inventory.adjust');

        // Movements (Audit Ledger)
        Route::get('/movements', [\App\Modules\Inventory\Http\Controllers\InventoryMovementController::class, 'index'])
            ->middleware('permission:inventory.movement');

        // Wastage
        Route::get('/wastages', [\App\Modules\Inventory\Http\Controllers\WastageController::class, 'index'])
            ->middleware('permission:wastage.view');
        Route::post('/wastages', [\App\Modules\Inventory\Http\Controllers\WastageController::class, 'store'])
            ->middleware('permission:wastage.create');
    });

    // POS, Tables, Orders & Payments
    Route::prefix('pos')->group(function () {
        // Tables & QR Codes
        Route::get('/tables', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'index'])
            ->middleware('permission:table.view');
        Route::post('/tables', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'store'])
            ->middleware('permission:table.create');
        Route::get('/tables/{id}', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'show'])
            ->middleware('permission:table.view');
        Route::put('/tables/{id}', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'update'])
            ->middleware('permission:table.update');
        Route::delete('/tables/{id}', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'destroy'])
            ->middleware('permission:table.delete');
        Route::post('/tables/{id}/qr', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'generateQr'])
            ->middleware('permission:qr.generate');
        Route::get('/tables/{id}/qr', [\App\Modules\PosOrder\Http\Controllers\TableController::class, 'getQrCodes'])
            ->middleware('permission:qr.view');

        // Orders
        Route::get('/orders', [\App\Modules\PosOrder\Http\Controllers\OrderController::class, 'index'])
            ->middleware('permission:order.view');
        Route::post('/orders', [\App\Modules\PosOrder\Http\Controllers\OrderController::class, 'store'])
            ->middleware('permission:order.create');
        Route::get('/orders/{id}', [\App\Modules\PosOrder\Http\Controllers\OrderController::class, 'show'])
            ->middleware('permission:order.view');
        Route::put('/orders/{id}/status', [\App\Modules\PosOrder\Http\Controllers\OrderController::class, 'updateStatus'])
            ->middleware('permission:order.update');
        Route::post('/orders/{id}/cancel', [\App\Modules\PosOrder\Http\Controllers\OrderController::class, 'cancel'])
            ->middleware('permission:order.cancel');

        // Payments
        Route::get('/orders/{id}/payments', [\App\Modules\PosOrder\Http\Controllers\PaymentController::class, 'index'])
            ->middleware('permission:payment.view');
        Route::post('/orders/{id}/payments', [\App\Modules\PosOrder\Http\Controllers\PaymentController::class, 'store'])
            ->middleware('permission:payment.process');
    });
});

/*
|--------------------------------------------------------------------------
| Public Customer Routes (QR Ordering)
|--------------------------------------------------------------------------
|
| Used by dine-in customers scanning a table QR code. No auth required.
|
*/
Route::prefix('public')->group(function () {
    Route::get('/qr/{token}', [\App\Modules\PosOrder\Http\Controllers\PublicQrController::class, 'resolve']);
    Route::post('/qr/{token}/order', [\App\Modules\PosOrder\Http\Controllers\PublicQrController::class, 'order']);
});



