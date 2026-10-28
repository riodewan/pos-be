<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinanceReconciliationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $paymentsQuery = Payment::with('order')
            ->where('status', 'SUCCESS')
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate);

        $payments = $paymentsQuery->get();

        $grossSales = (float) $payments->sum('amount');
        $totalMdr = (float) $payments->sum('mdr_fee');
        $netSettlement = (float) $payments->sum('net_amount');

        if ($netSettlement == 0 && $grossSales > 0) {
            $netSettlement = $grossSales - $totalMdr;
        }

        // Breakdown by channel/method
        $methodsBreakdown = $payments->groupBy('method')->map(function ($group, $method) {
            $gross = (float) $group->sum('amount');
            $mdr = (float) $group->sum('mdr_fee');
            $net = (float) $group->sum('net_amount');
            if ($net == 0) $net = $gross - $mdr;

            return [
                'method' => $method,
                'count' => $group->count(),
                'gross_amount' => $gross,
                'mdr_fee' => $mdr,
                'net_amount' => $net,
            ];
        })->values();

        // Recent reconciliation records
        $reconciliations = $payments->sortByDesc('paid_at')->take(20)->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'order_number' => $payment->order?->order_number ?? '-',
                'method' => $payment->method,
                'gross_amount' => (float) $payment->amount,
                'mdr_fee' => (float) $payment->mdr_fee,
                'net_amount' => (float) ($payment->net_amount > 0 ? $payment->net_amount : ($payment->amount - $payment->mdr_fee)),
                'settlement_status' => $payment->settlement_status ?? 'SELESAI',
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'message' => 'Finance reconciliation summary retrieved successfully.',
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'totals' => [
                    'gross_sales' => $grossSales,
                    'total_mdr' => $totalMdr,
                    'net_settlement' => $netSettlement,
                    'transaction_count' => $payments->count(),
                ],
                'methods_breakdown' => $methodsBreakdown,
                'reconciliations' => $reconciliations,
            ],
        ]);
    }
}
