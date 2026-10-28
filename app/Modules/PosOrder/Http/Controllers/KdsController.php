<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\OrderStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class KdsController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function queue(Request $request): JsonResponse
    {
        $statusFilter = $request->query('status');

        $query = Order::with(['orderItems.product', 'table'])
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'PREPARING', 'READY']);

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', strtoupper($statusFilter));
        }

        $orders = $query->orderBy('created_at', 'asc')->get();

        $formatted = $orders->map(function ($order) {
            $created = $order->created_at;
            $elapsedMinutes = $created ? (int) abs(now()->diffInMinutes($created)) : 0;

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'table_name' => $order->table?->name ?? 'Takeaway',
                'customer_name' => $order->customer_name ?? 'Pelanggan',
                'channel' => $order->channel ?? 'DINE_IN',
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
                'elapsed_minutes' => $elapsedMinutes,
                'notes' => $order->notes,
                'items' => $order->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? 'Menu',
                    'quantity' => (int) $item->quantity,
                    'notes' => $item->notes,
                ]),
            ];
        });

        return response()->json([
            'message' => 'KDS active orders retrieved successfully.',
            'data' => $formatted,
            'summary' => [
                'total_queue' => $formatted->count(),
                'preparing' => $formatted->where('status', 'PREPARING')->count(),
                'ready' => $formatted->where('status', 'READY')->count(),
                'pending' => $formatted->whereIn('status', ['PENDING', 'CONFIRMED'])->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        try {
            $order = $this->orderService->updateStatus($order, $validated['status']);

            return response()->json([
                'message' => 'Order status updated successfully.',
                'data' => $order,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
