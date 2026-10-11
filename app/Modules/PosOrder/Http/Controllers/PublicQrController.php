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
}
