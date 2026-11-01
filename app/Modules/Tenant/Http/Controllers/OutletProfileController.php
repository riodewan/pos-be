<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OutletProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenantId = AppContext::tenantId();
        $storeId = $request->query('store_id') ?: $request->header('X-Store-Id') ?: AppContext::storeId();

        $query = Store::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $store = $storeId ? $query->where('id', $storeId)->first() : $query->first();

        if (!$store) {
            return response()->json([
                'message' => 'Outlet tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Outlet profile retrieved successfully.',
            'data' => $store,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenantId = AppContext::tenantId();
        $storeId = $request->input('store_id')
            ?: $request->query('store_id')
            ?: $request->header('X-Store-Id')
            ?: AppContext::storeId();

        $query = Store::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $store = $storeId ? $query->where('id', $storeId)->first() : $query->first();

        if (!$store) {
            return response()->json([
                'message' => 'Outlet tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string'],
            'logo_url' => ['nullable', 'string'],
            'thumbnail_url' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'operational_status' => ['nullable', 'string', 'in:OPEN,CLOSED,TEMPORARILY_CLOSED'],
            'publication_status' => ['nullable', 'string', 'in:PUBLISHED,DRAFT,HIDDEN'],
            'operating_hours' => ['nullable', 'array'],
            'receipt_config' => ['nullable', 'array'],
            'facilities' => ['nullable', 'array'],
            'photos' => ['nullable', 'array'],
        ]);

        $store->update($validated);

        return response()->json([
            'message' => 'Outlet profile updated successfully.',
            'data' => $store->fresh(),
        ]);
    }
}
