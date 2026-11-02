<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Auth\Models\User;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\Reporting\Models\CashShiftAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CashShiftAuditController extends Controller
{
    public function index(): JsonResponse
    {
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();

        $auditsQuery = CashShiftAudit::orderByDesc('shift_date')->orderByDesc('created_at')->limit(50);
        if ($storeId) {
            $auditsQuery->where('store_id', $storeId);
        } elseif ($tenantId) {
            $auditsQuery->whereIn('store_id', function ($sub) use ($tenantId) {
                $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
            });
        }
        $audits = $auditsQuery->get();
        $users = User::whereIn('id', $audits->pluck('cashier_id')->filter())->pluck('name', 'id');

        $data = $audits->map(function (CashShiftAudit $a) use ($users, $storeId, $tenantId) {
            $cashSales = (float) Payment::where('status', 'SUCCESS')
                ->where('method', 'CASH')
                ->whereDate('paid_at', $a->shift_date)
                ->whereHas('order', function ($q) use ($a, $storeId, $tenantId) {
                    $q->when($storeId, fn ($sq) => $sq->where('store_id', $storeId))
                      ->when(!$storeId && $tenantId, function ($sq) use ($tenantId) {
                          $sq->whereIn('store_id', function ($sub) use ($tenantId) {
                              $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
                          });
                      })
                      ->when($a->cashier_id, fn ($sq) => $sq->where('created_by', $a->cashier_id));
                })
                ->get()
                ->sum(fn ($p) => $p->net_amount > 0 ? (float) $p->net_amount : max(0, (float) $p->amount - (float) $p->mdr_fee));

            $expected = $a->opening_cash + $cashSales;
            $actual = $a->actual_cash ?? $expected;
            $variance = $actual - $expected;

            return [
                'id' => $a->id,
                'shift_name' => $a->shift_name,
                'cashier_name' => $users[$a->cashier_id] ?? '-',
                'date' => $a->shift_date->toDateString(),
                'opening_cash' => $a->opening_cash,
                'cash_sales' => $cashSales,
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'variance' => $variance,
                'status' => $variance == 0 ? 'Sesuai' : ($variance > 0 ? 'Selisih Lebih' : 'Selisih Kurang'),
            ];
        });

        return response()->json([
            'message' => 'Cash audits retrieved successfully.',
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_name' => ['required', 'string', 'max:100'],
            'shift_date' => ['nullable', 'date'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'actual_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $audit = CashShiftAudit::create([
            'store_id' => AppContext::storeId(),
            'cashier_id' => $request->user()?->id,
            'shift_name' => $validated['shift_name'],
            'shift_date' => $validated['shift_date'] ?? now()->toDateString(),
            'opening_cash' => $validated['opening_cash'],
            'actual_cash' => $validated['actual_cash'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Cash audit saved successfully.', 'data' => $audit], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $audit = CashShiftAudit::findOrFail($id);
        $validated = $request->validate([
            'opening_cash' => ['sometimes', 'numeric', 'min:0'],
            'actual_cash' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $audit->update($validated);

        return response()->json(['message' => 'Cash audit updated successfully.', 'data' => $audit]);
    }
}
