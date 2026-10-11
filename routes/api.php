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


