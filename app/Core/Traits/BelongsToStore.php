<?php

namespace App\Core\Traits;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    /**
     * Boot the BelongsToStore trait.
     */
    public static function bootBelongsToStore(): void
    {
        // Auto-assign store_id if store context is active
        static::creating(function (Model $model) {
            if (empty($model->store_id) && AppContext::hasStore()) {
                $model->store_id = AppContext::storeId();
            }
        });

        // Global scope to isolate queries to the current active store
        static::addGlobalScope('store', function (Builder $builder) {
            if (AppContext::hasStore()) {
                $builder->where($builder->getModel()->getTable() . '.store_id', AppContext::storeId());
            }
        });
    }

    /**
     * Relationship to the Store model.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Query scope to filter by a specific store ID.
     */
    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->withoutGlobalScope('store')->where($this->getTable() . '.store_id', $storeId);
    }

    /**
     * Query scope to filter by a specific tenant ID via the parent store.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('store')
            ->whereHas('store', fn ($sq) => $sq->where('tenant_id', $tenantId));
    }

    /**
     * Query scope to bypass the store global scope.
     */
    public function scopeWithoutStoreScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('store');
    }
}
