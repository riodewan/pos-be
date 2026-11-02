<?php

namespace App\Modules\Tenant\Services;

use App\Core\Enums\CommonStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreService
{
    /**
     * List all stores belonging to tenant, scoped to user access if restricted.
     */
    public function listStores(Tenant $tenant, ?User $user = null): Collection
    {
        $query = Store::where('tenant_id', $tenant->id);

        if ($user && !$user->isInternalAdmin()) {
            $userRoles = $user->userRoles()->where('tenant_id', $tenant->id)->get();
            $storeIds = $userRoles->pluck('store_id')->filter()->unique();
            $hasTenantWideRole = $userRoles->contains(fn ($r) => is_null($r->store_id));

            if (!$hasTenantWideRole && $storeIds->isNotEmpty()) {
                $query->whereIn('id', $storeIds);
            }
        }

        return $query->orderBy('created_at', 'asc')->get();
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
     * Create a new store for tenant (requires Super Admin approval if created by merchant).
     */
    public function createStore(Tenant $tenant, array $data, ?User $user = null): Store
    {
        $isInternal = $user && $user->isInternalAdmin();
        $status = $isInternal ? CommonStatus::ACTIVE : CommonStatus::PENDING;

        $store = Store::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            'status' => $status,
        ]);

        if (!$isInternal) {
            \App\Modules\Tenant\Models\PlatformApproval::create([
                'type' => 'STORE_CREATION',
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'user_id' => $user?->id,
                'title' => 'Pengajuan Cabang Baru - ' . $store->name,
                'description' => 'Permohonan pembukaan outlet/cabang baru oleh mitra ' . $tenant->name,
                'status' => 'PENDING',
                'payload' => [
                    'store_name' => $store->name,
                    'branch_code' => $store->code,
                    'address' => $store->address,
                    'phone' => $store->phone,
                    'category' => $data['category'] ?? 'Coffee Shop',
                    'tenant_name' => $tenant->name,
                    'submitted_by' => $user?->name ?? 'Owner',
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);
        }

        return $store;
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
