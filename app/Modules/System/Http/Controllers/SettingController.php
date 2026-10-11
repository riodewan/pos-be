<?php

namespace App\Modules\System\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\System\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * List effective resolved settings dictionary.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = $this->settingService->listResolvedSettings();

        return response()->json([
            'message' => 'Settings retrieved successfully.',
            'data' => $settings,
        ]);
    }

    /**
     * Retrieve a specific setting value.
     */
    public function show(string $key): JsonResponse
    {
        $value = $this->settingService->get($key);

        if ($value === null) {
            return response()->json([
                'message' => "Setting '{$key}' not found.",
            ], 404);
        }

        return response()->json([
            'message' => 'Setting retrieved successfully.',
            'data' => [
                'key' => $key,
                'value' => $value,
            ],
        ]);
    }

    /**
     * Upsert a setting or batch of settings.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = AppContext::tenantId();
        $storeId = $request->input('store_id') ?: AppContext::storeId();

        // Support both single key-value and batch settings
        if ($request->has('settings')) {
            $validated = $request->validate([
                'settings' => ['required', 'array'],
                'store_id' => ['nullable', 'string'],
            ]);

            $saved = $this->settingService->setBatch($validated['settings'], $tenantId, $storeId);

            return response()->json([
                'message' => 'Settings updated successfully.',
                'data' => $saved,
            ]);
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string'],
            'store_id' => ['nullable', 'string'],
        ]);

        $setting = $this->settingService->set($validated['key'], $validated['value'], $tenantId, $storeId);

        return response()->json([
            'message' => 'Setting updated successfully.',
            'data' => $setting,
        ]);
    }
}
