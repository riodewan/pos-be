<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\OrderSource;
use App\Core\Enums\OrderStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->listOrders(
            status: $request->query('status'),
            tableId: $request->query('table_id'),
            source: $request->query('source'),
            channel: $request->query('channel'),
            search: $request->query('search'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            perPage: (int) $request->query('per_page', 20)
        );

        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['nullable', 'string'],
            'source' => ['nullable', Rule::enum(OrderSource::class)],
            'channel' => ['nullable', 'string', Rule::in(['DINE_IN', 'TAKEAWAY', 'DELIVERY', 'QR_ORDER'])],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $order = $this->orderService->createPosOrder($validated, $request->user()?->id);

            return response()->json([
                'message' => 'Order created successfully.',
                'data' => $order,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->orderService->getOrder($id);

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
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

    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $order = $this->orderService->cancelOrder($order, $validated['reason'] ?? null);

            return response()->json([
                'message' => 'Order cancelled successfully.',
                'data' => $order,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
