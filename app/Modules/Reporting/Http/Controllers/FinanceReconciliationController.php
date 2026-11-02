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
        $storeId = \App\Core\Context\AppContext::storeId();
        $tenantId = \App\Core\Context\AppContext::tenantId();

        $paymentsQuery = Payment::with(['order.store', 'order.orderItems.product'])
            ->whereHas('order', function ($q) use ($storeId, $tenantId) {
                $q->when($storeId, fn ($sq) => $sq->where('store_id', $storeId))
                  ->when(!$storeId && $tenantId, function ($sq) use ($tenantId) {
                      $sq->whereIn('store_id', function ($sub) use ($tenantId) {
                          $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
                      });
                  });
            })
            ->where('status', 'SUCCESS')
            ->whereDate('paid_at', '>=', $startDate)
            ->whereDate('paid_at', '<=', $endDate);

        $payments = $paymentsQuery->get();

        $grossSales = (float) $payments->sum('amount');
        $totalMdr = (float) $payments->sum('mdr_fee');
        $totalTax = (float) $payments->sum(fn ($p) => (float) ($p->order?->tax_amount ?? 0));
        $netSalesBeforeTax = (float) $payments->sum(fn ($p) => max(0, (float) ($p->order?->subtotal ?? 0) - (float) ($p->order?->discount_amount ?? 0)));
        $netSettlement = (float) $payments->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

        $digitalNetSettlement = (float) $payments
            ->filter(fn ($p) => ($p->method instanceof \BackedEnum ? $p->method->value : (string) $p->method) !== 'CASH')
            ->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

        $cashNetSettlement = (float) $payments
            ->filter(fn ($p) => ($p->method instanceof \BackedEnum ? $p->method->value : (string) $p->method) === 'CASH')
            ->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

        // Breakdown by channel/method
        $methodsBreakdown = $payments->groupBy(fn ($p) => $p->method instanceof \BackedEnum ? $p->method->value : (string) $p->method)->map(function ($group, $method) {
            $gross = (float) $group->sum('amount');
            $mdr = (float) $group->sum('mdr_fee');
            $tax = (float) $group->sum(fn ($p) => (float) ($p->order?->tax_amount ?? 0));
            $netSales = (float) $group->sum(fn ($p) => max(0, (float) ($p->order?->subtotal ?? 0) - (float) ($p->order?->discount_amount ?? 0)));
            $net = (float) $group->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

            return [
                'method' => $method,
                'count' => $group->count(),
                'gross_amount' => $gross,
                'net_sales' => $netSales,
                'tax_amount' => $tax,
                'mdr_fee' => $mdr,
                'net_amount' => $net,
            ];
        })->values();

        // Recent reconciliation records
        $reconciliations = $payments->sortByDesc('paid_at')->take(30)->map(function ($payment) {
            $methodStr = $payment->method instanceof \BackedEnum ? $payment->method->value : (string) $payment->method;
            $netSales = max(0, (float) ($payment->order?->subtotal ?? 0) - (float) ($payment->order?->discount_amount ?? 0));
            $taxAmount = (float) ($payment->order?->tax_amount ?? 0);
            $netAmount = (float) ($payment->net_amount > 0 ? $payment->net_amount : max(0, (float) $payment->amount - (float) $payment->mdr_fee));

            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'order_number' => $payment->order?->order_number ?? '-',
                'customer_name' => $payment->order?->customer_name ?: 'Pelanggan Umum',
                'method' => $methodStr,
                'gross_amount' => (float) $payment->amount,
                'net_sales' => $netSales,
                'tax_amount' => $taxAmount,
                'mdr_fee' => (float) $payment->mdr_fee,
                'net_amount' => $netAmount,
                'settlement_status' => $methodStr === 'CASH' ? 'SELESAI' : ($payment->settlement_status ?? 'SIAP_CAIR'),
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
                    'net_sales' => $netSalesBeforeTax,
                    'total_tax' => $totalTax,
                    'total_mdr' => $totalMdr,
                    'net_settlement' => $netSettlement,
                    'digital_net_settlement' => $digitalNetSettlement,
                    'cash_net_settlement' => $cashNetSettlement,
                    'transaction_count' => $payments->count(),
                ],
                'methods_breakdown' => $methodsBreakdown,
                'reconciliations' => $reconciliations,
            ],
        ]);
    }
}
