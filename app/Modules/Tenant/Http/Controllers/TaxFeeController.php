<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\System\Services\SettingService;
use App\Modules\Tenant\Models\StoreTaxFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TaxFeeController extends Controller
{
    protected function parseNumericPercent(string $raw): float
    {
        return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $raw));
    }

    protected function syncStoreTaxToSettings(string $storeId, ?string $tenantId): void
    {
        $settingService = app(SettingService::class);
        $activeTaxes = StoreTaxFee::where('store_id', $storeId)
            ->where('type', 'Pajak')
            ->where('is_active', true)
            ->get();

        $totalTaxPct = 0.0;
        foreach ($activeTaxes as $tax) {
            if ($tax->value_type === 'percent') {
                $totalTaxPct += $this->parseNumericPercent((string) $tax->value);
            }
        }

        $settingService->set('tax_percentage', (string) round($totalTaxPct, 2), $tenantId, $storeId);
        if ($tenantId) {
            $settingService->set('tax_percentage', (string) round($totalTaxPct, 2), $tenantId, null);
        }
    }

    public function index(): JsonResponse
    {
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();
        $settingService = app(SettingService::class);

        // Sync Platform Fee (Biaya) from Internal Admin tenant setting
        $rawPlatformFee = $settingService->get('platform_fee_percentage', null, $tenantId, null);
        $existingFees = StoreTaxFee::where('type', 'Biaya')->get();

        if ($rawPlatformFee === null) {
            $firstPercentFee = $existingFees->firstWhere('value_type', 'percent');
            $platformFeePct = ($firstPercentFee && $firstPercentFee->is_active)
                ? $this->parseNumericPercent((string) $firstPercentFee->value)
                : 0.0;
            if ($tenantId) {
                $settingService->set('platform_fee_percentage', (string) $platformFeePct, $tenantId, null);
            }
        } else {
            $platformFeePct = (float) $rawPlatformFee;
        }

        $primaryFee = $existingFees->first();
        if ($primaryFee) {
            $primaryFee->update([
                'name' => 'Biaya Layanan Platform',
                'type' => 'Biaya',
                'value_type' => 'percent',
                'value' => $platformFeePct . '%',
                'applies_to' => 'Semua Transaksi',
                'is_active' => $platformFeePct > 0,
            ]);
            foreach ($existingFees->slice(1) as $dup) {
                $dup->delete();
            }
        } else {
            StoreTaxFee::create([
                'store_id' => $storeId,
                'name' => 'Biaya Layanan Platform',
                'type' => 'Biaya',
                'value_type' => 'percent',
                'value' => $platformFeePct . '%',
                'applies_to' => 'Semua Transaksi',
                'is_active' => $platformFeePct > 0,
            ]);
        }

        if ($storeId) {
            $this->syncStoreTaxToSettings($storeId, $tenantId);
        }

        $taxesFees = StoreTaxFee::orderBy('type', 'desc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function (StoreTaxFee $item) {
                $arr = $item->toArray();
                $arr['is_platform_managed'] = $item->type === 'Biaya';
                return $arr;
            });

        return response()->json([
            'message' => 'Taxes and fees retrieved successfully.',
            'data' => $taxesFees,
            'meta' => [
                'platform_fee_percentage' => $platformFeePct,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:Pajak,Biaya'],
            'value_type' => ['nullable', 'string', 'in:percent,fixed'],
            'value' => ['required', 'string', 'max:50'],
            'applies_to' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($validated['type'] ?? 'Pajak') === 'Biaya') {
            return response()->json([
                'message' => 'Persentase Biaya Layanan / Komisi Platform diatur secara terpusat oleh Internal Admin sesuai kesepakatan mitra.',
            ], 422);
        }

        $numVal = $this->parseNumericPercent($validated['value']);
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();

        $taxFee = StoreTaxFee::create([
            'store_id' => $storeId,
            'name' => $validated['name'],
            'type' => 'Pajak',
            'value_type' => 'percent',
            'value' => $numVal . '%',
            'applies_to' => $validated['applies_to'] ?? 'Semua Menu',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($storeId) {
            $this->syncStoreTaxToSettings($storeId, $tenantId);
        }

        return response()->json([
            'message' => 'Pengaturan Pajak berhasil disimpan.',
            'data' => array_merge($taxFee->toArray(), ['is_platform_managed' => false]),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);

        if ($taxFee->type === 'Biaya') {
            return response()->json([
                'message' => 'Persentase Biaya Layanan / Komisi Platform hanya dapat diubah oleh Internal Admin.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'value' => ['sometimes', 'required', 'string', 'max:50'],
            'applies_to' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['value'])) {
            $numVal = $this->parseNumericPercent($validated['value']);
            $validated['value'] = $numVal . '%';
            $validated['value_type'] = 'percent';
        }

        $taxFee->update($validated);

        if ($taxFee->store_id) {
            $this->syncStoreTaxToSettings($taxFee->store_id, AppContext::tenantId());
        }

        return response()->json([
            'message' => 'Pengaturan Pajak berhasil diperbarui.',
            'data' => array_merge($taxFee->toArray(), ['is_platform_managed' => false]),
        ]);
    }

    public function toggle(string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);

        if ($taxFee->type === 'Biaya') {
            return response()->json([
                'message' => 'Status Biaya Layanan / Komisi Platform diatur oleh Internal Admin.',
            ], 422);
        }

        $taxFee->update(['is_active' => !$taxFee->is_active]);

        if ($taxFee->store_id) {
            $this->syncStoreTaxToSettings($taxFee->store_id, AppContext::tenantId());
        }

        return response()->json([
            'message' => 'Status Pajak berhasil diperbarui.',
            'data' => array_merge($taxFee->toArray(), ['is_platform_managed' => false]),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);

        if ($taxFee->type === 'Biaya') {
            return response()->json([
                'message' => 'Biaya Layanan / Komisi Platform tidak dapat dihapus oleh mitra.',
            ], 422);
        }

        $storeId = $taxFee->store_id;
        $taxFee->delete();

        if ($storeId) {
            $this->syncStoreTaxToSettings($storeId, AppContext::tenantId());
        }

        return response()->json([
            'message' => 'Pengaturan Pajak berhasil dihapus.',
        ]);
    }
}
