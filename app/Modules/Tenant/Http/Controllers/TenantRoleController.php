<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Core\Enums\RoleScope;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Tenant\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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
            ->withCount(['userRoles as staff_count' => function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }])
            ->orderBy('name')
            ->get();

        return $this->success(RoleResource::collection($roles));
    }

    /**
     * Get single role details.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = AppContext::tenant();

        $role = Role::where('tenant_id', $tenant->id)
            ->with('permissions')
            ->withCount(['userRoles as staff_count' => function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }])
            ->findOrFail($id);

        return $this->success(new RoleResource($role));
    }

    /**
     * Create custom role for this tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = AppContext::tenant();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $role = Role::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'scope' => RoleScope::TENANT,
            'is_default' => false,
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permissions'])) {
            $permIds = Permission::whereIn('id', $validated['permissions'])
                ->orWhereIn('name', $validated['permissions'])
                ->pluck('id');
            $role->permissions()->sync($permIds);
        }

        $role->load('permissions');
        $role->staff_count = 0;

        return $this->success(new RoleResource($role), 'Peran berhasil dibuat.', Response::HTTP_CREATED);
    }

    /**
     * Update role and its permissions.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = AppContext::tenant();

        $role = Role::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        if (isset($validated['name']) && !$role->is_default) {
            $role->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $role->description = $validated['description'];
        }
        $role->save();

        if (isset($validated['permissions'])) {
            $permIds = Permission::whereIn('id', $validated['permissions'])
                ->orWhereIn('name', $validated['permissions'])
                ->pluck('id');
            $role->permissions()->sync($permIds);
        }

        $role->load('permissions');
        $role->loadCount(['userRoles as staff_count' => function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }]);

        return $this->success(new RoleResource($role), 'Peran berhasil diperbarui.');
    }

    /**
     * Delete custom role.
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = AppContext::tenant();

        $role = Role::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($role->is_default || in_array(strtolower($role->name), ['owner', 'manager'])) {
            return $this->error('Peran sistem utama tidak dapat dihapus.', Response::HTTP_FORBIDDEN);
        }

        $hasUsers = UserRole::where('tenant_id', $tenant->id)->where('role_id', $role->id)->exists();
        if ($hasUsers) {
            return $this->error('Peran ini sedang digunakan oleh staf aktif dan tidak dapat dihapus.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role->permissions()->detach();
        $role->delete();

        return $this->success(null, 'Peran berhasil dihapus.');
    }

    /**
     * Get all master permissions available in the system.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        return $this->success($permissions);
    }
}
