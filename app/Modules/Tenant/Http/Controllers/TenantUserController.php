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
use Illuminate\Http\Request;
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
     * Update user details and/or role in this tenant.
     */
    public function update(Request $request, string $userId): JsonResponse
    {
        $tenant = AppContext::tenant();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:100',
            'role_id' => 'nullable|string|exists:roles,id',
            'store_id' => 'nullable|string|exists:stores,id',
            'roles' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'password' => 'nullable|string|min:8',
        ]);

        try {
            $membership = $this->tenantService->updateMember($tenant, $userId, $validated);
            return $this->success(new TenantUserResource($membership), 'Data staf berhasil diperbarui.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Toggle active status of a team member.
     */
    public function toggleStatus(string $userId): JsonResponse
    {
        $tenant = AppContext::tenant();

        try {
            $user = $this->tenantService->toggleMemberStatus($tenant, $userId);
            return $this->success([
                'id' => $user->id,
                'is_active' => $user->is_active,
            ], 'Status staf berhasil diubah.');
        } catch (\Exception $e) {
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
