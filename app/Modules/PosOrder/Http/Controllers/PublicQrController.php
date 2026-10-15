<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Services\OrderService;
use App\Modules\System\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class PublicQrController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected SettingService $settingService
    ) {}

    /**
     * Public QR Resolution.
     * Resolves store, table, active products, and settings using scanned QR token.
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

        if ($qrCode->table->status !== TableStatus::ACTIVE) {
            return response()->json([
                'message' => 'Meja saat ini sedang tidak aktif atau tidak tersedia.',
            ], 422);
        }

        if ($qrCode->store->status !== CommonStatus::ACTIVE) {
            return response()->json([
                'message' => 'Outlet/Toko saat ini sedang tidak beroperasi.',
            ], 422);
        }

        $products = Product::withoutGlobalScope('store')
            ->where('store_id', $qrCode->store_id)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get();

        $activeOrder = Order::withoutGlobalScope('store')
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'PREPARING', 'READY'])
            ->with(['orderItems.product'])
            ->latest()
            ->first();

        $taxPct = (float) $this->settingService->get('tax_percentage', $qrCode->store_id, $qrCode->store->tenant_id, 0);
        $servicePct = (float) $this->settingService->get('service_charge_percentage', $qrCode->store_id, $qrCode->store->tenant_id, 0);

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
                'settings' => [
                    'tax_percentage' => $taxPct,
                    'service_charge_percentage' => $servicePct,
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
