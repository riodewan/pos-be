<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Services\IpaymuService;
use App\Modules\PosOrder\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected IpaymuService $ipaymuService
    ) {}

    public function index(string $orderId): JsonResponse
    {
        $payments = $this->paymentService->listPaymentsForOrder($orderId);

        return response()->json([
            'message' => 'Payments retrieved successfully.',
            'data' => $payments,
        ]);
    }

    public function store(Request $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $payment = $this->paymentService->processPayment($order, $validated, $request->user()?->id);

            return response()->json([
                'message' => 'Payment processed successfully.',
                'data' => $payment,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function listTransactions(Request $request): JsonResponse
    {
        $transactions = $this->paymentService->listTransactions(
            status: $request->query('status'),
            method: $request->query('method'),
            search: $request->query('search'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            perPage: (int) $request->query('per_page', 20)
        );

        return response()->json([
            'message' => 'Transactions retrieved successfully.',
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * Generate dynamic QRIS payment via iPaymu for a POS order.
     */
    public function createIpaymuQris(Request $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

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
            Log::error('Failed to generate iPaymu QRIS', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check transaction status with iPaymu for a POS order.
     * If paid, automatically processes payment, depletes inventory, and completes order.
     */
    public function checkIpaymuStatus(Request $request, string $orderId): JsonResponse
    {
        $order = Order::with('payments')->findOrFail($orderId);

        // If order already has successful payment
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

        // Get iPaymu transaction id from request or cache
        $trxId = $request->query('transaction_id');
        if (!$trxId) {
            $cached = Cache::get("ipaymu_order_{$order->id}");
            $trxId = $cached['transaction_id'] ?? null;
        }

        if (!$trxId) {
            return response()->json([
                'message' => 'Data transaksi iPaymu tidak ditemukan untuk pesanan ini.',
                'is_paid' => false,
            ], 404);
        }

        try {
            $status = $this->ipaymuService->checkTransaction($trxId);

            if ($status['is_paid']) {
                $payment = $this->paymentService->processPayment($order, [
                    'method' => PaymentMethod::QRIS,
                    'amount' => (float) $order->total_amount,
                    'provider' => 'IPAYMU',
                    'provider_reference' => (string) $status['transaction_id'],
                    'metadata' => [
                        'ipaymu_transaction_id' => $status['transaction_id'],
                        'session_id' => $status['session_id'],
                        'paid_date' => $status['paid_date'],
                        'channel' => 'IPAYMU_QRIS',
                    ],
                    'auto_complete' => true,
                ], $request->user()?->id);

                return response()->json([
                    'message' => 'Pembayaran QRIS iPaymu berhasil dikonfirmasi.',
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

    /**
     * Webhook notification listener from iPaymu server.
     * Public endpoint exempt from CSRF / Sanctum auth.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('iPaymu webhook received', $payload);

        $referenceId = $payload['reference_id'] ?? null;
        $trxId = $payload['trx_id'] ?? $payload['transaction_id'] ?? null;
        $statusCode = (string) ($payload['status_code'] ?? $payload['status'] ?? '');
        $statusText = strtolower((string) ($payload['status'] ?? ''));

        $isSuccess = $statusCode === '1' || $statusText === 'berhasil';

        if (!$isSuccess) {
            return response()->json(['status' => 'ignored', 'reason' => 'Status is not successful']);
        }

        // Find Order by reference_id (order_number)
        $order = null;
        if ($referenceId) {
            $order = Order::withoutGlobalScope('store')
                ->where('order_number', $referenceId)
                ->first();
        }

        if (!$order && $trxId) {
            $orderId = Cache::get("ipaymu_trx_{$trxId}");
            if ($orderId) {
                $order = Order::withoutGlobalScope('store')->find($orderId);
            }
        }

        if (!$order) {
            Log::warning('iPaymu webhook: Order not found', ['reference_id' => $referenceId, 'trx_id' => $trxId]);
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        // Check if already paid
        $hasPaid = Payment::where('order_id', $order->id)
            ->where('status', PaymentStatus::SUCCESS)
            ->exists();

        if ($hasPaid) {
            return response()->json(['status' => 'already_processed']);
        }

        try {
            $this->paymentService->processPayment($order, [
                'method' => PaymentMethod::QRIS,
                'amount' => (float) $order->total_amount,
                'provider' => 'IPAYMU',
                'provider_reference' => (string) $trxId,
                'metadata' => [
                    'ipaymu_webhook' => true,
                    'trx_id' => $trxId,
                    'payload' => $payload,
                ],
                'auto_complete' => $order->source === \App\Core\Enums\OrderSource::POS,
            ]);

            Log::info('iPaymu webhook: Order payment completed successfully', ['order_id' => $order->id]);
            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::error('iPaymu webhook error processing payment', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
