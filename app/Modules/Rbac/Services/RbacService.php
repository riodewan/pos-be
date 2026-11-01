<?php

namespace App\Modules\Rbac\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\RoleScope;
use App\Modules\Auth\Models\User;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\RolePermission;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RbacService
{
    /**
     * Assign a TENANT role to a user, with optional store scope.
     * Enforces Decision #4 & Correction #5 validation rules.
     */
    public function assignRole(User $user, Role $role, string $tenantId, ?string $storeId = null): UserRole
    {
        if ($role->scope === RoleScope::INTERNAL) {
            throw new InvalidArgumentException('INTERNAL roles cannot be assigned to tenant or store scopes.');
        }

        if ($role->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Role does not belong to the specified tenant.');
        }

        if ($storeId !== null) {
            $store = Store::find($storeId);
            if (!$store || $store->tenant_id !== $tenantId) {
                throw new InvalidArgumentException('The specified store does not belong to the target tenant.');
            }
        }

        // Check if assignment already exists
        $existing = UserRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return UserRole::create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
        ]);
    }

    /**
     * Remove a role assignment from a user.
     */
    public function revokeRole(User $user, Role $role, string $tenantId, ?string $storeId = null): bool
    {
        $query = UserRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('tenant_id', $tenantId);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        } else {
            $query->whereNull('store_id');
        }

        return (bool) $query->delete();
    }

    /**
     * Get all active permission names for a user in the given tenant/store context.
     */
    public function getUserPermissions(User $user, ?string $tenantId = null, ?string $storeId = null): array
    {
        // Internal platform admins have all permissions
        if ($user->isInternalAdmin()) {
            return Permission::pluck('name')->all();
        }

        $tenantId = $tenantId ?: AppContext::tenantId();
        $storeId = $storeId ?: AppContext::storeId();

        if (!$tenantId) {
            return [];
        }

        $userRoles = UserRole::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->when($storeId, function ($query, $storeId) {
                $query->where(function ($q) use ($storeId) {
                    $q->whereNull('store_id')
                      ->orWhere('store_id', $storeId);
                });
            })
            ->with('role.permissions')
            ->get();

        $permissions = [];
        foreach ($userRoles as $userRole) {
            if ($userRole->role && $userRole->role->permissions) {
                foreach ($userRole->role->permissions as $permission) {
                    $permissions[$permission->name] = true;
                }
            }
        }

        return array_keys($permissions);
    }

    /**
     * Check if a user has a specific permission in the given context.
     */
    public function userHasPermission(User $user, string $permission, ?string $tenantId = null, ?string $storeId = null): bool
    {
        if ($user->isInternalAdmin()) {
            return true;
        }

        $permissions = $this->getUserPermissions($user, $tenantId, $storeId);

        return in_array($permission, $permissions, true);
    }

    /**
     * Check if a user has one or more roles in the given context.
     */
    public function userHasRole(User $user, string|array $roleNames, ?string $tenantId = null, ?string $storeId = null): bool
    {
        if ($user->isInternalAdmin()) {
            return true;
        }

        $roleNames = (array) $roleNames;
        $tenantId = $tenantId ?: AppContext::tenantId();
        $storeId = $storeId ?: AppContext::storeId();

        if (!$tenantId) {
            return false;
        }

        return UserRole::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->when($storeId, function ($query, $storeId) {
                $query->where(function ($q) use ($storeId) {
                    $q->whereNull('store_id')
                      ->orWhere('store_id', $storeId);
                });
            })
            ->whereHas('role', function ($q) use ($roleNames) {
                $q->whereIn('name', $roleNames);
            })
            ->exists();
    }

    /**
     * Seed default tenant roles (Owner, Manager, Inventory, Cashier) for a new Tenant.
     */
    public function createDefaultTenantRoles(Tenant $tenant): array
    {
        $allPermissions = Permission::all()->keyBy('name');

        $roleDefinitions = [
            'Owner' => [
                'description' => 'Akses penuh ke seluruh konfigurasi toko, data keuangan, laporan, dan staf.',
                'permissions' => $allPermissions->keys()->all(),
            ],
            'Manager' => [
                'description' => 'Mengelola operasional harian outlet, inventori, stok, dan memantau pesanan.',
                'permissions' => $allPermissions->keys()->reject(function ($name) {
                    return str_starts_with($name, 'tenant.delete') || str_starts_with($name, 'billing.');
                })->values()->all(),
            ],
            'Kasir' => [
                'description' => 'Menjalankan kasir POS, menerima pesanan, dan mencetak struk pembayaran.',
                'permissions' => [
                    'store.view',
                    'order.view', 'order.create', 'order.update', 'order.cancel',
                    'payment.view', 'payment.process',
                    'table.view', 'table.update',
                    'qr.view', 'qr.generate',
                    'product.view', 'menu.view',
                ],
            ],
            'Inventory' => [
                'description' => 'Mengelola stok bahan baku, pembelian (PO), supplier, dan penyesuaian stok.',
                'permissions' => [
                    'store.view',
                    'inventory.view', 'inventory.adjust', 'inventory.movement',
                    'purchase.view', 'purchase.create', 'purchase.update', 'purchase.receive', 'purchase.cancel',
                    'wastage.view', 'wastage.create', 'wastage.approve',
                    'supplier.view', 'supplier.create', 'supplier.update',
                    'ingredient.view', 'ingredient.create', 'ingredient.update',
                    'product.view', 'recipe.view', 'unit.view',
                ],
            ],
            'Dapur' => [
                'description' => 'Akses Kitchen Display (KDS), melihat resep porsi, dan konfirmasi hidangan siap disajikan.',
                'permissions' => [
                    'store.view',
                    'order.view', 'order.update',
                    'recipe.view',
                    'ingredient.view',
                    'product.view', 'menu.view',
                ],
            ],
        ];

        $createdRoles = [];

        foreach ($roleDefinitions as $roleName => $def) {
            $role = Role::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'scope' => RoleScope::TENANT,
                    'is_default' => true,
                    'description' => $def['description'],
                ]
            );

            // Sync permissions
            $permissionIds = [];
            foreach ($def['permissions'] as $permName) {
                if (isset($allPermissions[$permName])) {
                    RolePermission::firstOrCreate(
                        [
                            'role_id' => $role->id,
                            'permission_id' => $allPermissions[$permName]->id,
                        ],
                        [
                            'id' => (string) Str::ulid(),
                        ]
                    );
                }
            }

            $createdRoles[$roleName] = $role;
        }

        return $createdRoles;
    }
}
