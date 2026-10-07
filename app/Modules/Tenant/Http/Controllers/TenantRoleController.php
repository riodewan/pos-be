<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Rbac\Models\Role;
use App\Modules\Tenant\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;

class TenantRoleController extends Controller
{
    use ApiResponse;

    /**
     * List all available roles and permissions for this tenant.
     */
    public function index(): JsonResponse
    {
        $tenant = AppContext::tenant();

        $roles = Role::where('tenant_id', $tenant->id)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return $this->success(RoleResource::collection($roles));
    }
}
