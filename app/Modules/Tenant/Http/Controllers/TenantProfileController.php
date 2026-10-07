<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Tenant\Http\Requests\UpdateTenantProfileRequest;
use App\Modules\Tenant\Http\Resources\TenantResource;
use App\Modules\Tenant\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Get active tenant profile.
     */
    public function show(): JsonResponse
    {
        $tenant = AppContext::tenant();
        $profile = $this->tenantService->getProfile($tenant);

        return $this->success(new TenantResource($profile));
    }

    /**
     * Update active tenant profile.
     */
    public function update(UpdateTenantProfileRequest $request): JsonResponse
    {
        $tenant = AppContext::tenant();
        $updated = $this->tenantService->updateProfile($tenant, $request->validated());

        return $this->success(new TenantResource($updated), 'Tenant profile updated successfully.');
    }
}
