<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\QrStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class PublicQrController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Public QR Resolution.
     * Resolves store, table, and active products using scanned QR token.
     */
    public function resolve(string $token): JsonResponse
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $token)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->with(['store', 'table'])
            ->first();

        if (!$qrCode || !$qrCode->table || !$qrCode->store) {
            return response()->json([
                'message' => 'Invalid or inactive QR code.',
            ], 404);
        }

        $products = Product::withoutGlobalScope('store')
            ->where('store_id', $qrCode->store_id)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get();

        $activeOrder = \App\Modules\PosOrder\Models\Order::withoutGlobalScope('store')
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'PREPARING', 'READY'])
            ->with(['orderItems.product'])
            ->latest()
            ->first();

        return response()->json([
            'message' => 'QR code resolved successfully.',
            'data' => [
                'token' => $qrCode->token,
                'store' => [
                    'id' => $qrCode->store->id,
                    'name' => $qrCode->store->name,
                    'code' => $qrCode->store->code,
                ],
                'table' => [
                    'id' => $qrCode->table->id,
                    'name' => $qrCode->table->name,
                    'code' => $qrCode->table->code,
                    'capacity' => $qrCode->table->capacity,
                ],
                'active_order' => $activeOrder,
                'products' => $products,
            ],
        ]);
    }

    /**
     * Public QR Customer Order.
     * Allows dine-in customer to place order via scanned table QR token.
     */
    public function order(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $order = $this->orderService->createQrOrder($token, $validated);

            return response()->json([
                'message' => 'Order placed successfully.',
                'data' => $order,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Public QR Order Status Tracking.
     * Allows customer to check status of their placed order using QR token and order ID.
     */
    public function showOrder(string $token, string $orderId): JsonResponse
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $token)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if (!$qrCode) {
            return response()->json([
                'message' => 'Invalid or inactive QR code.',
            ], 404);
        }

        $order = \App\Modules\PosOrder\Models\Order::withoutGlobalScope('store')
            ->where('id', $orderId)
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->with(['table', 'orderItems.product', 'payments'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found for this table.',
            ], 404);
        }

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ]);
    }
}
