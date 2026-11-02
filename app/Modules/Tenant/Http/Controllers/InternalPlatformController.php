<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\OrderStatus;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\PosOrder\Models\Order;
use App\Modules\System\Models\AuditLog;
use App\Modules\Tenant\Models\PlatformApproval;
use App\Modules\Tenant\Models\PlatformMasterCategory;
use App\Modules\Tenant\Models\PlatformSubscriptionPlan;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $totalUsers = User::count();
        $totalOrders = Order::withoutGlobalScope('store')->where('status', OrderStatus::COMPLETED)->count();
        $totalGmv = (float) Order::withoutGlobalScope('store')->where('status', OrderStatus::COMPLETED)->sum('total_amount');
        $pendingApprovals = PlatformApproval::where('status', 'PENDING')->count();

        $recentTenants = Tenant::withCount(['stores', 'tenantUsers'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentApprovals = PlatformApproval::with(['tenant', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $this->success([
            'kpis' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'total_stores' => $totalStores,
                'total_users' => $totalUsers,
                'total_orders' => $totalOrders,
                'total_gmv' => $totalGmv,
                'pending_approvals' => $pendingApprovals,
            ],
            'recent_tenants' => $recentTenants,
            'recent_approvals' => $recentApprovals,
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
            ->paginate((int) $request->query('per_page', 10));

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
     * Update tenant basic details.
     */
    public function updateTenant(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:tenants,email,' . $tenant->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:ACTIVE,SUSPENDED,INACTIVE'],
        ]);

        $tenant->update($validated);

        return $this->success($tenant, 'Data tenant berhasil diperbarui.');
    }

    /**
     * Update tenant status (ACTIVE / SUSPENDED / INACTIVE).
     */
    public function updateTenantStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:ACTIVE,SUSPENDED,INACTIVE'],
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => $validated['status']]);

        return $this->success($tenant, "Status tenant diubah menjadi {$validated['status']}.");
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
            ->paginate((int) $request->query('per_page', 10));

        return $this->success($stores);
    }

    /**
     * List all approval requests.
     */
    public function listApprovals(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $type = $request->query('type');
        $search = $request->query('search');

        $approvals = PlatformApproval::with(['tenant', 'reviewer'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 10));

        return $this->success($approvals);
    }

    /**
     * Approve or reject an approval request.
     */
    public function actionApproval(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:APPROVE,REJECT'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $approval = PlatformApproval::findOrFail($id);

        $newStatus = $validated['action'] === 'APPROVE' ? 'APPROVED' : 'REJECTED';

        $approval->update([
            'status' => $newStatus,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        // Auto execute actions if needed (e.g. approve tenant verification or outlet)
        if ($newStatus === 'APPROVED') {
            if (in_array($approval->type, ['KYB_VERIFICATION', 'TENANT_REGISTRATION']) && $approval->tenant_id) {
                $tenant = Tenant::find($approval->tenant_id);
                if ($tenant) {
                    $tenant->update(['status' => 'ACTIVE']);

                    // Ensure the tenant has at least one active store
                    if ($tenant->stores()->count() === 0) {
                        $tenant->stores()->create([
                            'name' => $tenant->name . ' - Outlet Pusat',
                            'phone' => $tenant->phone ?: '08123456789',
                            'address' => $tenant->address ?: 'Indonesia',
                            'status' => \App\Core\Enums\CommonStatus::ACTIVE,
                        ]);
                    }
                }
            } elseif ($approval->type === 'STORE_CREATION' && $approval->store_id) {
                Store::where('id', $approval->store_id)->update(['status' => \App\Core\Enums\CommonStatus::ACTIVE]);
            }
        } elseif ($newStatus === 'REJECTED') {
            if (in_array($approval->type, ['KYB_VERIFICATION', 'TENANT_REGISTRATION']) && $approval->tenant_id) {
                Tenant::where('id', $approval->tenant_id)->update(['status' => 'PENDING']);
            } elseif ($approval->type === 'STORE_CREATION' && $approval->store_id) {
                Store::where('id', $approval->store_id)->update(['status' => \App\Core\Enums\CommonStatus::REJECTED]);
            }
        }

        return $this->success($approval->fresh(['tenant', 'reviewer']), "Pengajuan berhasil di-{$validated['action']}.");
    }

    /**
     * Master Data: Business Categories.
     */
    public function listMasterCategories(): JsonResponse
    {
        $categories = PlatformMasterCategory::orderBy('sort_order', 'asc')->get();
        return $this->success($categories);
    }

    public function storeMasterCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:platform_master_categories,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $category = PlatformMasterCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'Store',
            'is_active' => true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($category, 'Kategori master berhasil ditambahkan.', Response::HTTP_CREATED);
    }

    public function updateMasterCategory(Request $request, string $id): JsonResponse
    {
        $category = PlatformMasterCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category->update($validated);

        return $this->success($category, 'Kategori master berhasil diperbarui.');
    }

    public function deleteMasterCategory(string $id): JsonResponse
    {
        $category = PlatformMasterCategory::findOrFail($id);
        $category->delete();

        return $this->success(null, 'Kategori master berhasil dihapus.');
    }

    /**
     * Master Data: Subscription Plans.
     */
    public function listSubscriptionPlans(): JsonResponse
    {
        $plans = PlatformSubscriptionPlan::orderBy('price', 'asc')->get();
        return $this->success($plans);
    }

    public function updateSubscriptionPlan(Request $request, string $id): JsonResponse
    {
        $plan = PlatformSubscriptionPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'max_outlets' => ['sometimes', 'integer', 'min:1'],
            'max_staff' => ['sometimes', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan->update($validated);

        return $this->success($plan, 'Paket langganan berhasil diperbarui.');
    }

    /**
     * Platform-wide Audit Logs.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $action = $request->query('action');
        $module = $request->query('module');
        $search = $request->query('search');

        $logs = AuditLog::with(['user', 'tenant'])
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($module, fn ($q) => $q->where('module', $module))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('action', 'ilike', "%{$search}%")
                        ->orWhere('ip_address', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('tenant', fn ($tq) => $tq->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 10));

        return $this->success($logs);
    }
}
