<?php

namespace App\Modules\System\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\AuditAction;
use App\Modules\System\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * List audit logs with context scoping, filtering and pagination.
     */
    public function listLogs(
        ?string $action = null,
        ?string $userId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $tenantId = AppContext::tenantId();
        $storeId = AppContext::storeId();

        return AuditLog::with(['user.userRoles.role', 'store'])
            ->when($tenantId, function ($q) use ($tenantId) {
                $q->where(function ($sub) use ($tenantId) {
                    $sub->where('tenant_id', $tenantId)
                        ->orWhere(function ($userQuery) use ($tenantId) {
                            $userQuery->whereNull('tenant_id')
                                ->whereHas('user.userRoles', fn ($ur) => $ur->where('tenant_id', $tenantId));
                        });
                });
            })
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', Carbon::parse($startDate)->startOfDay()))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', Carbon::parse($endDate)->endOfDay()))
            ->when($search, function ($q) use ($search) {
                $term = strtolower(trim($search));
                $q->where(function ($sq) use ($term) {
                    $sq->whereRaw('LOWER(ip_address) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(user_agent) LIKE ?', ["%{$term}%"])
                        ->orWhereHas('user', function ($uq) use ($term) {
                            $uq->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Append-only record an audit log entry.
     */
    public function record(
        AuditAction|string $action,
        ?string $userId = null,
        ?string $module = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $tenantId = null,
        ?string $storeId = null
    ): AuditLog {
        if (is_string($action)) {
            $action = AuditAction::from($action);
        }

        return AuditLog::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId ?? AppContext::tenantId(),
            'store_id' => $storeId ?? AppContext::storeId(),
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
