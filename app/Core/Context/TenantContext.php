<?php

namespace App\Core\Context;

use App\Modules\Tenant\Models\Tenant;
use RuntimeException;

class TenantContext
{
    protected ?Tenant $tenant = null;

    /**
     * Set the current tenant instance.
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Set tenant by its ULID or slug.
     */
    public function setTenantByIdOrSlug(string $identifier): ?Tenant
    {
        $tenant = Tenant::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        $this->setTenant($tenant);

        return $tenant;
    }

    /**
     * Get the current active tenant model.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current active tenant ULID.
     */
    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }

    /**
     * Determine if a tenant context is currently set.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Get active tenant or throw exception.
     *
     * @throws RuntimeException
     */
    public function check(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException('Tenant context is required but has not been resolved.');
        }

        return $this->tenant;
    }

    /**
     * Clear the tenant context.
     */
    public function clear(): void
    {
        $this->tenant = null;
    }
}
