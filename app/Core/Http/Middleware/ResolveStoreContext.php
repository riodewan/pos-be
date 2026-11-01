<?php

namespace App\Core\Http\Middleware;

use App\Core\Context\StoreContext;
use App\Core\Context\TenantContext;
use App\Core\Enums\CommonStatus;
use App\Core\Traits\ApiResponse;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreContext
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected StoreContext $storeContext
    ) {}

    public function handle(Request $request, Closure $next, bool $optional = false): Response
    {
        $storeIdentifier = $request->header('X-Store-Id')
            ?: $request->header('X-Store-Code')
            ?: $request->route('store')
            ?: $request->route('store_id')
            ?: $request->query('store_id')
            ?: $request->input('store_id');

        if (!$storeIdentifier) {
            if ($optional) {
                return $next($request);
            }

            return $this->error(
                'Store identifier is required (provide X-Store-Id or X-Store-Code header).',
                Response::HTTP_BAD_REQUEST
            );
        }

        $query = Store::query()->where(function ($q) use ($storeIdentifier) {
            $q->where('id', $storeIdentifier)
              ->orWhere('code', $storeIdentifier);
        });

        if ($this->tenantContext->hasTenant()) {
            $query->where('tenant_id', $this->tenantContext->getTenantId());
        }

        $store = $query->first();

        if (!$store) {
            return $this->notFound(
                $this->tenantContext->hasTenant()
                    ? 'Store not found in the current tenant.'
                    : 'Store not found.'
            );
        }

        // Ensure tenant context is synced
        if (!$this->tenantContext->hasTenant()) {
            $this->tenantContext->setTenant($store->tenant);
        }

        // Authorization check if user is authenticated
        $user = $request->user();
        if ($user) {
            if (!$user->is_active) {
                return $this->unauthorized('User account has been deactivated.');
            }

            if (!$user->belongsToTenant($store->tenant_id)) {
                return $this->forbidden('You do not have access to this tenant.');
            }

            if (!$user->hasAccessToStore($store->id, $store->tenant_id)) {
                return $this->forbidden('You do not have access to this store.');
            }
        }

        // Enforce active store status (internal admin is exempt)
        $storeStatus = $store->status instanceof CommonStatus
            ? $store->status->value
            : (is_string($store->status) ? $store->status : 'ACTIVE');

        if ($storeStatus !== 'ACTIVE' && (!$user || !$user->isInternalAdmin())) {
            return $this->forbidden('This store is currently inactive.');
        }

        // Bind to store context
        $this->storeContext->setStore($store);

        return $next($request);
    }
}
