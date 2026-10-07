<?php

namespace App\Core\Context;

use App\Modules\Tenant\Models\Store;
use RuntimeException;

class StoreContext
{
    protected ?Store $store = null;

    /**
     * Set the current store instance.
     */
    public function setStore(?Store $store): void
    {
        $this->store = $store;
    }

    /**
     * Set store by its ULID or code, optionally scoped to a tenant.
     */
    public function setStoreByIdOrCode(string $identifier, ?string $scopedTenantId = null): ?Store
    {
        $query = Store::where(function ($q) use ($identifier) {
            $q->where('id', $identifier)
              ->orWhere('code', $identifier);
        });

        if ($scopedTenantId !== null) {
            $query->where('tenant_id', $scopedTenantId);
        }

        $store = $query->first();

        $this->setStore($store);

        return $store;
    }

    /**
     * Get the current active store model.
     */
    public function getStore(): ?Store
    {
        return $this->store;
    }

    /**
     * Get the current active store ULID.
     */
    public function getStoreId(): ?string
    {
        return $this->store?->id;
    }

    /**
     * Determine if a store context is currently set.
     */
    public function hasStore(): bool
    {
        return $this->store !== null;
    }

    /**
     * Get active store or throw exception.
     *
     * @throws RuntimeException
     */
    public function check(): Store
    {
        if ($this->store === null) {
            throw new RuntimeException('Store context is required but has not been resolved.');
        }

        return $this->store;
    }

    /**
     * Clear the store context.
     */
    public function clear(): void
    {
        $this->store = null;
    }
}
