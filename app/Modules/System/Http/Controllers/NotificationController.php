<?php

namespace App\Modules\System\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\System\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = AppContext::tenantId();

        $baseQuery = Notification::where('tenant_id', $tenantId);

        if (AppContext::hasStore()) {
            $storeId = AppContext::storeId();
            $baseQuery->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });
        }

        // Summary counts for filter badges
        $counts = [
            'all' => (clone $baseQuery)->count(),
            'pesanan' => (clone $baseQuery)->where('category', 'Pesanan')->count(),
            'reservasi' => (clone $baseQuery)->where('category', 'Reservasi')->count(),
            'inventori' => (clone $baseQuery)->where('category', 'Inventori')->count(),
            'sistem' => (clone $baseQuery)->where('category', 'Sistem')->count(),
            'unread' => (clone $baseQuery)->where('is_read', false)->count(),
            'read' => (clone $baseQuery)->where('is_read', true)->count(),
        ];

        $query = clone $baseQuery;

        if ($request->filled('category') && $request->query('category') !== 'Semua') {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        } elseif ($request->query('status') === 'BELUM_DIBACA') {
            $query->where('is_read', false);
        } elseif ($request->query('status') === 'SUDAH_DIBACA') {
            $query->where('is_read', true);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('subtitle', 'like', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 50);
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications->items(),
            'unread_count' => $counts['unread'],
            'counts' => $counts,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    public function markUnread(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->update([
            'is_read' => false,
            'read_at' => null,
        ]);

        return response()->json([
            'message' => 'Notification marked as unread.',
            'data' => $notification,
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $tenantId = AppContext::tenantId();

        $query = Notification::where('tenant_id', $tenantId)
            ->where('is_read', false);

        if (AppContext::hasStore()) {
            $storeId = AppContext::storeId();
            $query->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });
        }

        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $tenantId = AppContext::tenantId();

        $query = Notification::where('tenant_id', $tenantId);

        if (AppContext::hasStore()) {
            $storeId = AppContext::storeId();
            $query->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });
        }

        if ($request->boolean('read_only', true)) {
            $query->where('is_read', true);
        }

        $deletedCount = $query->delete();

        return response()->json([
            'message' => "{$deletedCount} notifications cleared successfully.",
        ]);
    }
}
