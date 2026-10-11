<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\PaymentMethod;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
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
}
