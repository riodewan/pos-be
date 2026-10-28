<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\StorePaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $methods = StorePaymentMethod::orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Payment methods retrieved successfully.',
            'data' => $methods,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon_type' => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $maxSort = StorePaymentMethod::max('sort_order') ?? 0;

        $method = StorePaymentMethod::create([
            'store_id' => AppContext::storeId(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'icon_type' => $validated['icon_type'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? ($maxSort + 1),
        ]);

        return response()->json([
            'message' => 'Payment method created successfully.',
            'data' => $method,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $method = StorePaymentMethod::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'type' => ['sometimes', 'required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon_type' => ['sometimes', 'required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $method->update($validated);

        return response()->json([
            'message' => 'Payment method updated successfully.',
            'data' => $method,
        ]);
    }

    public function toggle(string $id): JsonResponse
    {
        $method = StorePaymentMethod::findOrFail($id);
        $method->update(['is_active' => !$method->is_active]);

        return response()->json([
            'message' => 'Status updated successfully.',
            'data' => $method,
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'string'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($validated['items'] as $item) {
            StorePaymentMethod::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $methods = StorePaymentMethod::orderBy('sort_order', 'asc')->get();

        return response()->json([
            'message' => 'Payment methods reordered successfully.',
            'data' => $methods,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $method = StorePaymentMethod::findOrFail($id);
        $method->delete();

        return response()->json([
            'message' => 'Payment method deleted successfully.',
        ]);
    }
}
