<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\OrderStatus;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\PosOrder\Models\Order;
use App\Modules\System\Models\AuditLog;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalPlatformController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Platform Overview KPIs.
     */
    public function overview(): JsonResponse
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'ACTIVE')->count();
        $totalStores = Store::count();
        $totalOrders = Order::withoutGlobalScope('store')->where('status', OrderStatus::COMPLETED)->count();
        $totalGmv = (float) Order::withoutGlobalScope('store')->where('status', OrderStatus::COMPLETED)->sum('total_amount');

        $recentTenants = Tenant::withCount(['stores', 'tenantUsers'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $this->success([
            'kpis' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'total_stores' => $totalStores,
                'total_orders' => $totalOrders,
                'total_gmv' => $totalGmv,
            ],
            'recent_tenants' => $recentTenants,
        ]);
    }

    /**
     * List all tenants across platform (Paginated & Filterable).
     */
    public function listTenants(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $tenants = Tenant::withCount(['stores', 'tenantUsers'])
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                });
            })
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 15));

        return $this->success($tenants);
    }

    /**
     * Show single tenant details across platform.
     */
    public function showTenant(string $id): JsonResponse
    {
        $tenant = Tenant::with(['stores', 'tenantUsers.user'])
            ->withCount(['stores', 'tenantUsers'])
            ->findOrFail($id);

        return $this->success($tenant);
    }

    /**
     * Create a new tenant directly by platform administrator.
     */
    public function createTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tenants,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_code' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = $this->tenantService->registerTenant([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'owner_name' => $validated['owner_name'],
            'owner_email' => $validated['owner_email'],
            'password' => $validated['owner_password'],
            'password_confirmation' => $validated['owner_password'],
            'store_name' => $validated['store_name'] ?? ($validated['name'] . ' Main Outlet'),
            'store_code' => $validated['store_code'] ?? 'MAIN-01',
        ]);

        return $this->success($tenant, 'Tenant registered successfully by administrator.', Response::HTTP_CREATED);
    }

    /**
     * Update tenant status (ACTIVE / SUSPENDED).
     */
    public function updateTenantStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:ACTIVE,SUSPENDED,INACTIVE'],
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => $validated['status']]);

        return $this->success($tenant, "Tenant status updated to {$validated['status']}.");
    }

    /**
     * List all stores across all tenants.
     */
    public function listStores(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $tenantId = $request->query('tenant_id');

        $stores = Store::with('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 20));

        return $this->success($stores);
    }

    /**
     * Platform-wide Audit Logs.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::with(['user', 'tenant'])
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 25));

        return $this->success($logs);
    }
}
