<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Tenant\Http\Requests\AddTenantUserRequest;
use App\Modules\Tenant\Http\Requests\UpdateUserRolesRequest;
use App\Modules\Tenant\Http\Resources\TenantUserResource;
use App\Modules\Tenant\Services\TenantService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TenantUserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * List all team members in this tenant.
     */
    public function index(): JsonResponse
    {
        $tenant = AppContext::tenant();
        $members = $this->tenantService->getTenantMembers($tenant);

        return $this->success(TenantUserResource::collection($members));
    }

    /**
     * Add/Invite user to tenant.
     */
    public function store(AddTenantUserRequest $request): JsonResponse
    {
        $tenant = AppContext::tenant();

        try {
            $membership = $this->tenantService->addMember($tenant, $request->validated());
            return $this->success(new TenantUserResource($membership), 'User added to tenant successfully.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Update roles for a team member in this tenant.
     */
    public function updateRoles(UpdateUserRolesRequest $request, string $userId): JsonResponse
    {
        $tenant = AppContext::tenant();

        try {
            $this->tenantService->updateUserRoles($tenant, $userId, $request->validated()['roles']);
            return $this->success(null, 'User roles updated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove team member from this tenant.
     */
    public function destroy(string $userId): JsonResponse
    {
        $tenant = AppContext::tenant();

        try {
            $this->tenantService->removeMember($tenant, $userId);
            return $this->success(null, 'User removed from tenant successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
