<?php

namespace App\Core\Context;

use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;

class AppContext
{
    public static function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    public static function storeContext(): StoreContext
    {
        return app(StoreContext::class);
    }

    public static function tenant(): ?Tenant
    {
        return static::tenantContext()->getTenant();
    }

    public static function tenantId(): ?string
    {
        return static::tenantContext()->getTenantId();
    }

    public static function store(): ?Store
    {
        return static::storeContext()->getStore();
    }

    public static function storeId(): ?string
    {
        return static::storeContext()->getStoreId();
    }

    public static function hasTenant(): bool
    {
        return static::tenantContext()->hasTenant();
    }

    public static function hasStore(): bool
    {
        return static::storeContext()->hasStore();
    }

    public static function clear(): void
    {
        static::tenantContext()->clear();
        static::storeContext()->clear();
    }
}
