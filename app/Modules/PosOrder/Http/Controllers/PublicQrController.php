<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\CommonStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Services\IpaymuService;
use App\Modules\PosOrder\Services\OrderService;
use App\Modules\PosOrder\Services\PaymentService;
use App\Modules\System\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PublicQrController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected SettingService $settingService,
        protected IpaymuService $ipaymuService
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

        // Validasi QR sekali pakai: kedaluwarsa / kuota pemakaian habis
        if ($blockReason = $qrCode->orderingBlockReason()) {
            return response()->json([
                'message' => $blockReason,
            ], 410);
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

        // QR sekali pakai adalah sesi baru, jangan tampilkan pesanan aktif tamu lain di meja yang sama
        $activeOrder = $qrCode->type === QrCode::TYPE_DISPOSABLE ? null : Order::withoutGlobalScope('store')
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'PREPARING', 'READY'])
            ->with(['orderItems.product'])
            ->latest()
            ->first();

        $taxPct = (float) $this->settingService->get('tax_percentage', $qrCode->store_id, $qrCode->store->tenant_id, 0);
        if ($taxPct <= 0) {
            $activeTaxes = \App\Modules\Tenant\Models\StoreTaxFee::withoutGlobalScope('store')
                ->where('store_id', $qrCode->store_id)
                ->where('type', 'Pajak')
                ->where('is_active', true)
                ->get();
            foreach ($activeTaxes as $t) {
                if ($t->value_type === 'percent') {
                    $taxPct += (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $t->value));
                }
            }
        }
        $platformFeePct = (float) $this->settingService->get(
            'platform_fee_percentage',
            null,
            $qrCode->store->tenant_id,
            $this->settingService->get('service_charge_percentage', $qrCode->store_id, $qrCode->store->tenant_id, 0)
        );

        return response()->json([
            'message' => 'QR code resolved successfully.',
            'data' => [
                'token' => $qrCode->token,
                'qr' => [
                    'type' => $qrCode->type,
                    'label' => $qrCode->label,
                    'expires_at' => $qrCode->expires_at?->toIso8601String(),
                    'remaining_uses' => $qrCode->max_uses !== null
                        ? max(0, $qrCode->max_uses - $qrCode->used_count)
                        : null,
                ],
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
                    'tax_percentage' => round($taxPct, 2),
                    'service_charge_percentage' => round($platformFeePct, 2),
                    'platform_fee_percentage' => round($platformFeePct, 2),
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
            'payment_method' => ['nullable', 'string', 'in:QRIS,ONLINE,TRANSFER,EDC,CASH'],
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
     * Public QR Customer Direct Payment.
     * Allows customer to pay for their order via QRIS/Online directly at the table.
     */
    public function payOrder(Request $request, string $token, string $orderId): JsonResponse
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $token)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if (!$qrCode) {
            return response()->json(['message' => 'Invalid or inactive QR code.'], 404);
        }

        $order = Order::withoutGlobalScope('store')
            ->where('id', $orderId)
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->firstOrFail();

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:QRIS,ONLINE,TRANSFER,EDC,CASH'],
            'provider' => ['nullable', 'string'],
        ]);

        try {
            $paymentService = app(\App\Modules\PosOrder\Services\PaymentService::class);
            $payment = $paymentService->processPayment($order, [
                'method' => $validated['method'],
                'amount' => (float) $order->total_amount,
                'provider' => $validated['provider'] ?? 'QRIS_GATEWAY',
                'provider_reference' => 'QRIS-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'metadata' => [
                    'channel' => 'CUSTOMER_QR_SELF_SERVICE',
                    'paid_at' => now()->toIso8601String(),
                ],
                'auto_complete' => false,
            ]);

            return response()->json([
                'message' => 'Payment processed successfully.',
                'data' => $order->fresh(['table', 'orderItems.product', 'payments']),
            ]);
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

    /**
     * Generate dynamic QRIS payment via iPaymu for customer dine-in self-order.
     */
    public function createIpaymuQris(Request $request, string $token, string $orderId): JsonResponse
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $token)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if (!$qrCode) {
            return response()->json(['message' => 'Invalid or inactive QR code.'], 404);
        }

        $order = Order::withoutGlobalScope('store')
            ->where('id', $orderId)
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->firstOrFail();

        if ($order->status === \App\Core\Enums\OrderStatus::COMPLETED) {
            return response()->json(['message' => 'Pesanan ini sudah selesai dibayar.'], 422);
        }

        try {
            $qris = $this->ipaymuService->createQris($order, [
                'name' => $request->input('customer_name', $order->customer_name),
                'phone' => $request->input('customer_phone', $order->customer_phone),
                'email' => $request->input('customer_email'),
            ]);

            return response()->json([
                'message' => 'QRIS iPaymu berhasil dibuat.',
                'data' => $qris,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate customer iPaymu QRIS', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check transaction status with iPaymu for customer dine-in self-order.
     */
    public function checkIpaymuStatus(Request $request, string $token, string $orderId): JsonResponse
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $token)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if (!$qrCode) {
            return response()->json(['message' => 'Invalid or inactive QR code.'], 404);
        }

        $order = Order::withoutGlobalScope('store')
            ->where('id', $orderId)
            ->where('store_id', $qrCode->store_id)
            ->where('table_id', $qrCode->table_id)
            ->with(['orderItems.product', 'table', 'payments'])
            ->firstOrFail();

        $successPayment = $order->payments->firstWhere('status', PaymentStatus::SUCCESS);
        if ($successPayment) {
            return response()->json([
                'message' => 'Pesanan sudah lunas.',
                'is_paid' => true,
                'data' => [
                    'order' => $order,
                    'payment' => $successPayment,
                ],
            ]);
        }

        $trxId = $request->query('transaction_id');
        if (!$trxId) {
            $cached = Cache::get("ipaymu_order_{$order->id}");
            $trxId = $cached['transaction_id'] ?? null;
        }

        if (!$trxId) {
            return response()->json([
                'message' => 'Data transaksi iPaymu tidak ditemukan.',
                'is_paid' => false,
            ], 404);
        }

        try {
            $status = $this->ipaymuService->checkTransaction($trxId);

            if ($status['is_paid']) {
                $paymentService = app(PaymentService::class);
                $payment = $paymentService->processPayment($order, [
                    'method' => PaymentMethod::QRIS,
                    'amount' => (float) $order->total_amount,
                    'provider' => 'IPAYMU',
                    'provider_reference' => (string) $status['transaction_id'],
                    'metadata' => [
                        'channel' => 'CUSTOMER_QR_SELF_SERVICE',
                        'ipaymu_transaction_id' => $status['transaction_id'],
                        'session_id' => $status['session_id'],
                        'paid_date' => $status['paid_date'],
                    ],
                    'auto_complete' => false,
                ]);

                return response()->json([
                    'message' => 'Pembayaran berhasil dikonfirmasi.',
                    'is_paid' => true,
                    'data' => [
                        'order' => $order->fresh(['orderItems.product', 'table', 'payments']),
                        'payment' => $payment,
                    ],
                ]);
            }

            return response()->json([
                'message' => $status['status_desc'] ?: 'Menunggu pembayaran.',
                'is_paid' => false,
                'data' => $status,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'is_paid' => false,
            ], 422);
        }
    }
}
