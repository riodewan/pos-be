<?php

namespace App\Modules\Tenant\Services;

use App\Core\Enums\CommonStatus;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreService
{
    /**
     * List all stores belonging to tenant.
     */
    public function listStores(Tenant $tenant): Collection
    {
        return Store::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get single store by ID.
     */
    public function getStore(Tenant $tenant, string $storeId): Store
    {
        return Store::where('tenant_id', $tenant->id)
            ->where('id', $storeId)
            ->firstOrFail();
    }

    /**
     * Create a new store for tenant.
     */
    public function createStore(Tenant $tenant, array $data): Store
    {
        return Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            'status' => CommonStatus::ACTIVE,
        ]);
    }

    /**
     * Update an existing store.
     */
    public function updateStore(Store $store, array $data): Store
    {
        $store->update(array_filter([
            'name' => $data['name'] ?? null,
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($val) => !is_null($val)));

        return $store->fresh();
    }

    /**
     * Soft delete a store.
     * Enforces Decision #1: A Tenant must have at least one Store.
     */
    public function deleteStore(Tenant $tenant, Store $store): void
    {
        $activeStoresCount = Store::where('tenant_id', $tenant->id)->count();

        if ($activeStoresCount <= 1) {
            throw new InvalidArgumentException('Cannot delete the only store of a tenant. A tenant must have at least one store.');
        }

        $store->delete();
    }
}
