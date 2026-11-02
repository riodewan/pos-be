<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\OrderStatus;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Unit;
use App\Modules\PosOrder\Models\Order;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\System\Models\AuditLog;
use App\Modules\System\Models\Setting;
use App\Modules\System\Services\SettingService;
use App\Modules\Tenant\Models\PlatformApproval;
use App\Modules\Tenant\Models\PlatformMasterCategory;
use App\Modules\Tenant\Models\PlatformMasterItem;
use App\Modules\Tenant\Models\PlatformSubscriptionPlan;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\StoreTaxFee;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
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
     * Enrich tenant model with platform_fee_percentage and tax_percentage.
     */
    protected function enrichTenantFeeSettings(Tenant $tenant): Tenant
    {
        $settingService = app(SettingService::class);
        $firstStore = Store::where('tenant_id', $tenant->id)->first();

        $rawPlatformFee = $settingService->get('platform_fee_percentage', null, $tenant->id, null);
        if ($rawPlatformFee === null && $firstStore) {
            $feeRecord = StoreTaxFee::withoutGlobalScope('store')
                ->where('store_id', $firstStore->id)
                ->where('type', 'Biaya')
                ->where('is_active', true)
                ->first();
            if ($feeRecord && $feeRecord->value_type === 'percent') {
                $rawPlatformFee = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $feeRecord->value));
            }
        }
        $platformFeePct = $rawPlatformFee !== null ? (float) $rawPlatformFee : 0.0;

        $rawTax = $settingService->get('tax_percentage', $firstStore?->id, $tenant->id, null);
        if ($rawTax === null && $firstStore) {
            $taxRecord = StoreTaxFee::withoutGlobalScope('store')
                ->where('store_id', $firstStore->id)
                ->where('type', 'Pajak')
                ->where('is_active', true)
                ->first();
            if ($taxRecord) {
                $rawTax = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $taxRecord->value));
            }
        }
        $taxPct = $rawTax !== null ? (float) $rawTax : 10.0;

        $tenant->setAttribute('platform_fee_percentage', $platformFeePct);
        $tenant->setAttribute('tax_percentage', $taxPct);

        return $tenant;
    }

    /**
     * Sync tenant platform_fee_percentage and tax_percentage to settings & StoreTaxFee.
     */
    protected function syncTenantFeeSettings(Tenant $tenant, ?float $platformFeePct = null, ?float $taxPct = null): void
    {
        $settingService = app(SettingService::class);
        $stores = Store::where('tenant_id', $tenant->id)->get();

        if ($platformFeePct !== null) {
            $cleanFee = max(0, min(100, round($platformFeePct, 2)));
            $settingService->set('platform_fee_percentage', (string) $cleanFee, $tenant->id, null);

            foreach ($stores as $store) {
                $settingService->set('service_charge_percentage', (string) $cleanFee, $tenant->id, $store->id);

                // Keep a single platform fee record in StoreTaxFee
                $existingFees = StoreTaxFee::withoutGlobalScope('store')
                    ->where('store_id', $store->id)
                    ->where('type', 'Biaya')
                    ->get();

                $primaryFee = $existingFees->first();
                if ($primaryFee) {
                    $primaryFee->update([
                        'name' => 'Biaya Layanan Platform',
                        'type' => 'Biaya',
                        'value_type' => 'percent',
                        'value' => $cleanFee . '%',
                        'applies_to' => 'Semua Transaksi',
                        'is_active' => $cleanFee > 0,
                    ]);
                    foreach ($existingFees->slice(1) as $dup) {
                        $dup->delete();
                    }
                } elseif ($cleanFee > 0) {
                    StoreTaxFee::withoutGlobalScope('store')->create([
                        'store_id' => $store->id,
                        'name' => 'Biaya Layanan Platform',
                        'type' => 'Biaya',
                        'value_type' => 'percent',
                        'value' => $cleanFee . '%',
                        'applies_to' => 'Semua Transaksi',
                        'is_active' => true,
                    ]);
                }
            }
        }

        if ($taxPct !== null) {
            $cleanTax = max(0, min(100, round($taxPct, 2)));
            $settingService->set('tax_percentage', (string) $cleanTax, $tenant->id, null);

            foreach ($stores as $store) {
                $settingService->set('tax_percentage', (string) $cleanTax, $tenant->id, $store->id);

                $existingTaxes = StoreTaxFee::withoutGlobalScope('store')
                    ->where('store_id', $store->id)
                    ->where('type', 'Pajak')
                    ->get();

                $primaryTax = $existingTaxes->first();
                if ($primaryTax) {
                    $primaryTax->update([
                        'name' => $primaryTax->name ?: 'PB1 / Pajak Restoran',
                        'type' => 'Pajak',
                        'value_type' => 'percent',
                        'value' => $cleanTax . '%',
                        'applies_to' => 'Semua Menu',
                        'is_active' => $cleanTax > 0,
                    ]);
                    foreach ($existingTaxes->slice(1) as $dup) {
                        $dup->delete();
                    }
                } else {
                    StoreTaxFee::withoutGlobalScope('store')->create([
                        'store_id' => $store->id,
                        'name' => 'PB1 / Pajak Restoran',
                        'type' => 'Pajak',
                        'value_type' => 'percent',
                        'value' => $cleanTax . '%',
                        'applies_to' => 'Semua Menu',
                        'is_active' => $cleanTax > 0,
                    ]);
                }
            }
        }
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

        $tenants->getCollection()->transform(fn (Tenant $t) => $this->enrichTenantFeeSettings($t));

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

        $userRoles = UserRole::where('tenant_id', $tenant->id)
            ->with(['role', 'store'])
            ->get()
            ->groupBy('user_id');

        foreach ($tenant->tenantUsers as $tu) {
            $firstRole = $userRoles->get($tu->user_id)?->first();
            $tu->setAttribute('role', $firstRole?->role?->name ?? 'Staff');
            $tu->setAttribute('store_name', $firstRole?->store?->name ?? 'Semua Outlet');
        }

        $this->enrichTenantFeeSettings($tenant);

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
            'platform_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

        $this->syncTenantFeeSettings(
            $tenant,
            isset($validated['platform_fee_percentage']) ? (float) $validated['platform_fee_percentage'] : 0.0,
            isset($validated['tax_percentage']) ? (float) $validated['tax_percentage'] : 10.0
        );

        return $this->success($this->enrichTenantFeeSettings($tenant), 'Tenant registered successfully by administrator.', Response::HTTP_CREATED);
    }

    /**
     * Update tenant basic details & platform fee/tax configuration.
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
            'platform_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenant->update(collect($validated)->only(['name', 'email', 'phone', 'address', 'status'])->toArray());

        if (array_key_exists('platform_fee_percentage', $validated) || array_key_exists('tax_percentage', $validated)) {
            $this->syncTenantFeeSettings(
                $tenant,
                array_key_exists('platform_fee_percentage', $validated) && $validated['platform_fee_percentage'] !== null
                    ? (float) $validated['platform_fee_percentage']
                    : null,
                array_key_exists('tax_percentage', $validated) && $validated['tax_percentage'] !== null
                    ? (float) $validated['tax_percentage']
                    : null
            );
        }

        return $this->success($this->enrichTenantFeeSettings($tenant), 'Data & pengaturan biaya mitra berhasil diperbarui.');
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
                            'id' => (string) \Illuminate\Support\Str::ulid(),
                            'code' => 'PUSAT',
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
     * Master Data: Global Items (FACILITY, BUSINESS_TYPE, PAYMENT_METHOD, TAX_FEE, BANK).
     */
    public function listMasterItems(Request $request): JsonResponse
    {
        $group = $request->query('group');

        $items = PlatformMasterItem::when($group, fn ($q) => $q->where('group', strtoupper($group)))
            ->orderBy('group', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return $this->success($items);
    }

    public function storeMasterItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'in:FACILITY,BUSINESS_TYPE,PAYMENT_METHOD,TAX_FEE,BANK'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $code = $validated['code'] ?? Str::slug($validated['name'], '_');

        $item = PlatformMasterItem::create([
            'group' => $validated['group'],
            'name' => $validated['name'],
            'code' => $code,
            'icon' => $validated['icon'] ?? 'Sparkles',
            'description' => $validated['description'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($item, 'Item master global berhasil ditambahkan.', Response::HTTP_CREATED);
    }

    public function updateMasterItem(Request $request, string $id): JsonResponse
    {
        $item = PlatformMasterItem::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $item->update($validated);

        return $this->success($item, 'Item master global berhasil diperbarui.');
    }

    public function toggleMasterItem(string $id): JsonResponse
    {
        $item = PlatformMasterItem::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);

        return $this->success($item, 'Status item master berhasil diperbarui.');
    }

    public function deleteMasterItem(string $id): JsonResponse
    {
        $item = PlatformMasterItem::findOrFail($id);
        $item->delete();

        return $this->success(null, 'Item master global berhasil dihapus.');
    }

    /**
     * Master Data: Global Measurement Units.
     */
    public function listMasterUnits(): JsonResponse
    {
        $units = Unit::orderBy('name', 'asc')->get();
        return $this->success($units);
    }

    public function storeMasterUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:units,code'],
            'type' => ['required', 'string', 'in:WEIGHT,VOLUME,COUNT'],
        ]);

        $unit = Unit::create($validated);

        return $this->success($unit, 'Satuan ukur global berhasil ditambahkan.', Response::HTTP_CREATED);
    }

    public function updateMasterUnit(Request $request, string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:20', 'unique:units,code,' . $unit->id],
            'type' => ['sometimes', 'required', 'string', 'in:WEIGHT,VOLUME,COUNT'],
        ]);

        $unit->update($validated);

        return $this->success($unit, 'Satuan ukur global berhasil diperbarui.');
    }

    public function deleteMasterUnit(string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();

        return $this->success(null, 'Satuan ukur global berhasil dihapus.');
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

    /**
     * List all users across the platform with their tenant memberships & roles.
     */
    public function listUsers(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $filterType = strtoupper((string) $request->query('filter_type', 'ALL'));
        $tenantId = $request->query('tenant_id');

        $query = User::query()
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhereHas('tenants', fn ($tq) => $tq->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($tenantId, function ($q, $tenantId) {
                $q->whereHas('tenantUsers', fn ($tq) => $tq->withoutGlobalScope('tenant')->where('tenant_id', $tenantId));
            });

        if ($filterType === 'INTERNAL') {
            $query->where('is_internal', true);
        } elseif ($filterType === 'MITRA') {
            $query->where('is_internal', false)
                ->whereHas('tenantUsers', fn ($tq) => $tq->withoutGlobalScope('tenant'));
        } elseif ($filterType === 'UNASSIGNED') {
            $query->where('is_internal', false)
                ->whereDoesntHave('tenantUsers', fn ($tq) => $tq->withoutGlobalScope('tenant'));
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 10));

        $userIds = $users->getCollection()->pluck('id')->all();

        $tenantMemberships = TenantUser::withoutGlobalScope('tenant')
            ->whereIn('user_id', $userIds)
            ->with(['tenant.stores'])
            ->get()
            ->groupBy('user_id');

        $userRoles = UserRole::whereIn('user_id', $userIds)
            ->with(['role', 'store'])
            ->get()
            ->groupBy(fn ($ur) => $ur->user_id . ':' . $ur->tenant_id);

        $users->getCollection()->transform(function (User $u) use ($tenantMemberships, $userRoles) {
            $memberships = $tenantMemberships->get($u->id, collect());
            $tenantsData = $memberships->map(function ($m) use ($u, $userRoles) {
                $roleAssignment = $userRoles->get($u->id . ':' . $m->tenant_id)?->first();
                return [
                    'id' => $m->tenant?->id ?? $m->tenant_id,
                    'name' => $m->tenant?->name ?? 'Unknown Tenant',
                    'slug' => $m->tenant?->slug ?? '',
                    'status' => $m->tenant?->status ?? 'ACTIVE',
                    'role' => $roleAssignment?->role?->name ?? 'Owner',
                    'store_id' => $roleAssignment?->store_id,
                    'store_name' => $roleAssignment?->store?->name ?? 'Semua Outlet',
                ];
            })->values()->all();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'job_title' => $u->job_title,
                'is_internal' => (bool) $u->is_internal,
                'is_active' => (bool) ($u->is_active ?? true),
                'created_at' => $u->created_at,
                'tenants' => $tenantsData,
            ];
        });

        return $this->success($users);
    }

    /**
     * Create a new user account directly by Internal Admin (for Mitra or Internal).
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_mode' => ['required', 'string', 'in:EXISTING_TENANT,NEW_TENANT,UNASSIGNED_MITRA,INTERNAL_ADMIN'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            // For EXISTING_TENANT
            'tenant_id' => ['required_if:account_mode,EXISTING_TENANT', 'nullable', 'string', 'exists:tenants,id'],
            'role_name' => ['nullable', 'string', 'max:50'],
            'store_id' => ['nullable', 'string'],
            // For NEW_TENANT
            'tenant_name' => ['required_if:account_mode,NEW_TENANT', 'nullable', 'string', 'max:255'],
            'tenant_address' => ['nullable', 'string'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_code' => ['nullable', 'string', 'max:50'],
        ]);

        $email = strtolower(trim($validated['email']));
        $mode = $validated['account_mode'];

        if ($mode === 'EXISTING_TENANT') {
            $tenant = Tenant::findOrFail($validated['tenant_id']);
            $membership = $this->tenantService->addMember($tenant, [
                'name' => trim($validated['name']),
                'email' => $email,
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'role_name' => $validated['role_name'] ?? 'Owner',
                'store_id' => !empty($validated['store_id']) ? $validated['store_id'] : null,
                'is_active' => true,
            ]);

            return $this->success($membership->user, 'Akun pengguna berhasil dibuat dan ditautkan ke mitra.', Response::HTTP_CREATED);
        }

        if ($mode === 'NEW_TENANT') {
            $existingUser = User::whereRaw('LOWER(email) = ?', [$email])->exists();
            if ($existingUser) {
                return $this->error('Email pengguna sudah terdaftar di sistem.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tenantEmail = $email;
            if (Tenant::whereRaw('LOWER(email) = ?', [$tenantEmail])->exists()) {
                $parts = explode('@', $email);
                $tenantEmail = $parts[0] . '+' . strtolower(Str::random(4)) . '@' . ($parts[1] ?? 'mitra.com');
            }

            $tenant = $this->tenantService->registerTenant([
                'name' => trim($validated['tenant_name']),
                'email' => $tenantEmail,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['tenant_address'] ?? null,
                'owner_name' => trim($validated['name']),
                'owner_email' => $email,
                'password' => $validated['password'],
                'store_name' => !empty($validated['store_name']) ? trim($validated['store_name']) : (trim($validated['tenant_name']) . ' - Outlet Pusat'),
                'store_code' => !empty($validated['store_code']) ? trim($validated['store_code']) : 'PUSAT-01',
                'status' => 'ACTIVE',
            ]);

            $owner = User::whereRaw('LOWER(email) = ?', [$email])->first();

            return $this->success($owner, 'Akun Mitra beserta Brand & Outlet berhasil dibuat!', Response::HTTP_CREATED);
        }

        // UNASSIGNED_MITRA or INTERNAL_ADMIN
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return $this->error('Email sudah digunakan oleh akun lain.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::create([
            'id' => (string) Str::ulid(),
            'name' => trim($validated['name']),
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? ($mode === 'INTERNAL_ADMIN' ? 'Platform Admin' : 'Owner Mitra'),
            'password' => $validated['password'],
            'is_internal' => $mode === 'INTERNAL_ADMIN',
            'is_active' => true,
        ]);

        return $this->success(
            $user,
            $mode === 'INTERNAL_ADMIN'
                ? 'Akun Admin Internal berhasil dibuat!'
                : 'Akun Mitra baru berhasil dibuat (siap digunakan login untuk pengisian KYB).',
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing user account (info, password reset, status, and optional tenant assignment).
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'is_internal' => ['nullable', 'boolean'],
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            'role_name' => ['nullable', 'string', 'max:50'],
            'store_id' => ['nullable', 'string'],
        ]);

        if (isset($validated['email'])) {
            $email = strtolower(trim($validated['email']));
            $exists = User::whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return $this->error('Email sudah digunakan oleh akun lain.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->email = $email;
        }

        if (isset($validated['name'])) {
            $user->name = trim($validated['name']);
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (array_key_exists('job_title', $validated)) {
            $user->job_title = $validated['job_title'];
        }
        if (isset($validated['is_active'])) {
            $user->is_active = (bool) $validated['is_active'];
        }
        if (isset($validated['is_internal'])) {
            $user->is_internal = (bool) $validated['is_internal'];
        }
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        // If tenant_id is provided, also link or update their role in that tenant
        if (!empty($validated['tenant_id'])) {
            $tenant = Tenant::findOrFail($validated['tenant_id']);
            $this->tenantService->addMember($tenant, [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'role_name' => $validated['role_name'] ?? 'Owner',
                'store_id' => !empty($validated['store_id']) ? $validated['store_id'] : null,
                'is_active' => $user->is_active,
            ]);
        }

        return $this->success($user, 'Data akun pengguna berhasil diperbarui.');
    }

    /**
     * Toggle user active status.
     */
    public function toggleUserStatus(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return $this->success($user, 'Status aktif pengguna berhasil diperbarui.');
    }

    /**
     * Delete user account.
     */
    public function deleteUser(Request $request, string $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return $this->error('Tidak dapat menghapus akun Anda sendiri yang sedang aktif.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::findOrFail($id);
        UserRole::where('user_id', $user->id)->delete();
        TenantUser::withoutGlobalScope('tenant')->where('user_id', $user->id)->delete();
        $user->delete();

        return $this->success(null, 'Akun pengguna berhasil dihapus.');
    }
}
