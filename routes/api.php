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

