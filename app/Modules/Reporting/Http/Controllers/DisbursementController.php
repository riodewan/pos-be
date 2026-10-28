<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\Reporting\Models\Disbursement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DisbursementController extends Controller
{
    private const TRANSFER_FEE = 4500;

    private function availableBalance(): float
    {
        $payments = Payment::whereHas('order')->where('status', 'SUCCESS')->where('method', '!=', 'CASH')->get();
        $net = $payments->sum(fn ($p) => $p->net_amount > 0 ? $p->net_amount : ($p->amount - $p->mdr_fee));
        $withdrawn = Disbursement::where('status', '!=', 'Gagal')->sum('gross_amount');

        return max(0, (float) $net - (float) $withdrawn);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Disbursements retrieved successfully.',
            'data' => [
                'available_balance' => $this->availableBalance(),
                'transfer_fee' => self::TRANSFER_FEE,
                'items' => Disbursement::orderByDesc('requested_at')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:' . self::TRANSFER_FEE],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
        ]);

        if ($validated['amount'] > $this->availableBalance()) {
            return response()->json(['message' => 'Saldo siap cair tidak mencukupi.'], 422);
        }

        $count = Disbursement::whereDate('requested_at', now()->toDateString())->count() + 1;

        $disbursement = Disbursement::create([
            'store_id' => AppContext::storeId(),
            'batch_id' => sprintf('DIS-%s-%03d-%s', now()->format('Ymd'), $count, strtoupper(substr(uniqid(), -3))),
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'gross_amount' => $validated['amount'],
            'fee' => self::TRANSFER_FEE,
            'net_received' => $validated['amount'] - self::TRANSFER_FEE,
            'status' => 'Diproses',
            'requested_by' => $request->user()?->id,
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Disbursement requested successfully.',
            'data' => $disbursement,
        ], 201);
    }
}
