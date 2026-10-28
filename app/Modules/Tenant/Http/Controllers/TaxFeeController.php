<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\StoreTaxFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TaxFeeController extends Controller
{
    public function index(): JsonResponse
    {
        $taxesFees = StoreTaxFee::orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'message' => 'Taxes and fees retrieved successfully.',
            'data' => $taxesFees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:Pajak,Biaya'],
            'value_type' => ['required', 'string', 'in:percent,fixed'],
            'value' => ['required', 'string', 'max:50'],
            'applies_to' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $taxFee = StoreTaxFee::create([
            'store_id' => AppContext::storeId(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value_type' => $validated['value_type'],
            'value' => $validated['value'],
            'applies_to' => $validated['applies_to'] ?? 'Semua Menu',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Tax or fee created successfully.',
            'data' => $taxFee,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['sometimes', 'required', 'string', 'in:Pajak,Biaya'],
            'value_type' => ['sometimes', 'required', 'string', 'in:percent,fixed'],
            'value' => ['sometimes', 'required', 'string', 'max:50'],
            'applies_to' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $taxFee->update($validated);

        return response()->json([
            'message' => 'Tax or fee updated successfully.',
            'data' => $taxFee,
        ]);
    }

    public function toggle(string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);
        $taxFee->update(['is_active' => !$taxFee->is_active]);

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $taxFee,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $taxFee = StoreTaxFee::findOrFail($id);
        $taxFee->delete();

        return response()->json([
            'message' => 'Tax or fee deleted successfully.',
        ]);
    }
}
