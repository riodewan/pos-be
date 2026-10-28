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

        $query = Notification::where('tenant_id', $tenantId);

        if (AppContext::hasStore()) {
            $storeId = AppContext::storeId();
            $query->where(function ($q) use ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            });
        }

        if ($request->filled('category') && $request->query('category') !== 'Semua') {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $unreadCount = (clone $query)->where('is_read', false)->count();

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications->items(),
            'unread_count' => $unreadCount,
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
}
