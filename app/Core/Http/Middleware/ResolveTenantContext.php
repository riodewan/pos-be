<?php

namespace App\Core\Http\Middleware;

use App\Core\Context\TenantContext;
use App\Core\Traits\ApiResponse;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next, bool $optional = false): Response
    {
        $tenantIdentifier = $request->header('X-Tenant-Id')
            ?: $request->header('X-Tenant-Slug')
            ?: $request->route('tenant')
            ?: $request->route('tenant_id')
            ?: $request->query('tenant_id');

        if (!$tenantIdentifier) {
            if ($optional) {
                return $next($request);
            }

            return $this->error(
                'Tenant identifier is required (provide X-Tenant-Id or X-Tenant-Slug header).',
                Response::HTTP_BAD_REQUEST
            );
        }

        $tenant = Tenant::where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->first();

        if (!$tenant) {
            return $this->notFound('Tenant not found.');
        }

        // Authorization check if user is authenticated
        $user = $request->user();
        if ($user && !$user->belongsToTenant($tenant->id)) {
            return $this->forbidden('You do not have access to this tenant.');
        }

        // Bind to context
        $this->tenantContext->setTenant($tenant);

        return $next($request);
    }
}
