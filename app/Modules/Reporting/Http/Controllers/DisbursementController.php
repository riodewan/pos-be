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
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();

        $payments = Payment::whereHas('order', function ($q) use ($storeId, $tenantId) {
                $q->when($storeId, fn ($sq) => $sq->where('store_id', $storeId))
                  ->when(!$storeId && $tenantId, function ($sq) use ($tenantId) {
                      $sq->whereIn('store_id', function ($sub) use ($tenantId) {
                          $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
                      });
                  });
            })
            ->where('status', 'SUCCESS')
            ->where('method', '!=', 'CASH')
            ->get();

        $net = $payments->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

        $withdrawnQuery = Disbursement::where('status', '!=', 'Gagal');
        if ($storeId) {
            $withdrawnQuery->where('store_id', $storeId);
        } elseif ($tenantId) {
            $withdrawnQuery->whereIn('store_id', function ($sub) use ($tenantId) {
                $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
            });
        }
        $withdrawn = (float) $withdrawnQuery->sum('gross_amount');

        return max(0.0, (float) $net - $withdrawn);
    }

    public function index(): JsonResponse
    {
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();

        $itemsQuery = Disbursement::orderByDesc('requested_at');
        if ($storeId) {
            $itemsQuery->where('store_id', $storeId);
        } elseif ($tenantId) {
            $itemsQuery->whereIn('store_id', function ($sub) use ($tenantId) {
                $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
            });
        }

        return response()->json([
            'message' => 'Disbursements retrieved successfully.',
            'data' => [
                'available_balance' => $this->availableBalance(),
                'transfer_fee' => self::TRANSFER_FEE,
                'items' => $itemsQuery->get(),
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
            'status' => 'Berhasil',
            'requested_by' => $request->user()?->id,
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Disbursement requested successfully.',
            'data' => $disbursement,
        ], 201);
    }
}
