<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Tenant\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OutletProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $storeId = AppContext::storeId();
        $store = Store::findOrFail($storeId);

        return response()->json([
            'message' => 'Outlet profile retrieved successfully.',
            'data' => $store,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $storeId = AppContext::storeId();
        $store = Store::findOrFail($storeId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'thumbnail_url' => ['nullable', 'string', 'max:500'],
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
            'data' => $store,
        ]);
    }
}
