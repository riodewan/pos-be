<?php

namespace App\Core\Traits;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    public static function bootBelongsToTenant(): void
    {
        // Auto-assign tenant_id if tenant context is active
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && AppContext::hasTenant()) {
                $model->tenant_id = AppContext::tenantId();
            }
        });

        // Global scope to isolate queries to the current active tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (AppContext::hasTenant()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', AppContext::tenantId());
            }
        });
    }

    /**
     * Relationship to the Tenant model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Query scope to filter by a specific tenant ID.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')->where($this->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Query scope to bypass the tenant global scope.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
