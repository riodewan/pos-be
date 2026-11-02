<?php

namespace App\Modules\Tenant\Services;

use App\Core\Enums\CommonStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\Store;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantService
{
    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * Register a new Tenant with default store, owner user, and RBAC roles.
     */
    public function registerTenant(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $slug = Str::slug($data['name']);
            if (Tenant::where('slug', $slug)->exists()) {
                $slug .= '-' . Str::lower(Str::random(4));
            }

            $tenant = Tenant::create([
                'id' => (string) Str::ulid(),
                'name' => $data['name'],
                'slug' => $slug,
                'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => $data['status'] ?? 'ACTIVE',
            ]);

            // 1. Create Default Tenant Roles
            $roles = $this->rbacService->createDefaultTenantRoles($tenant);

            // 2. Create Default Store
            Store::create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => $data['store_name'] ?? ($data['name'] . ' Main Outlet'),
                'code' => $data['store_code'] ?? 'MAIN-01',
                'status' => CommonStatus::ACTIVE,
            ]);

            // 3. Create or find Owner User
            $ownerEmail = strtolower(trim($data['owner_email']));
            $owner = User::withTrashed()->whereRaw('LOWER(email) = ?', [$ownerEmail])->first();
            if (!$owner) {
                $owner = User::create([
                    'id' => (string) Str::ulid(),
                    'name' => trim($data['owner_name']),
                    'email' => $ownerEmail,
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'is_internal' => false,
                    'is_active' => true,
                ]);
            } else {
                if ($owner->trashed()) {
                    $owner->restore();
                }
                $owner->name = trim($data['owner_name']);
                $owner->email = $ownerEmail;
                if (!empty($data['password'])) {
                    $owner->password = $data['password'];
                }
                $owner->is_active = true;
                $owner->save();
            }

            // 4. Attach TenantUser pivot
            TenantUser::firstOrCreate([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
            ], [
                'id' => (string) Str::ulid(),
                'joined_at' => now(),
            ]);

            // 5. Assign Owner Role
            if (isset($roles['Owner'])) {
                $this->rbacService->assignRole($owner, $roles['Owner'], $tenant->id, null);
            }

            return $tenant->load(['stores']);
        });
    }

    /**
     * Get tenant profile with counts.
     */
    public function getProfile(Tenant $tenant): Tenant
    {
        return $tenant->loadCount(['stores', 'tenantUsers']);
    }

    /**
     * Update tenant profile info.
     */
    public function updateProfile(Tenant $tenant, array $data): Tenant
    {
        $tenant->update(array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ], fn ($val) => !is_null($val)));

        return $tenant->fresh()->loadCount(['stores', 'tenantUsers']);
    }

    /**
     * Get all members of a tenant with their user and role information.
     */
    public function getTenantMembers(Tenant $tenant): Collection
    {
        $members = TenantUser::where('tenant_id', $tenant->id)
            ->with(['user'])
            ->get();

        // Load roles specifically for this tenant
        $userIds = $members->pluck('user_id')->all();
        $rolesByUserId = UserRole::where('tenant_id', $tenant->id)
            ->whereIn('user_id', $userIds)
            ->with(['role', 'store'])
            ->get()
            ->groupBy('user_id');

        foreach ($members as $member) {
            $member->setRelation('roles', $rolesByUserId->get($member->user_id, collect()));
        }

        return $members;
    }

    /**
     * Resolve a tenant role by ID or name (ensuring default roles exist).
     */
    protected function resolveTenantRole(Tenant $tenant, ?string $roleId = null, ?string $roleName = null): Role
    {
        $this->rbacService->createDefaultTenantRoles($tenant);

        if (!empty($roleId)) {
            $role = Role::where('tenant_id', $tenant->id)->find($roleId);
            if ($role) {
                return $role;
            }
        }

        if (!empty($roleName)) {
            $normalized = trim($roleName);
            $aliasMap = [
                'cashier' => 'Kasir',
                'gudang' => 'Inventory',
                'inventori' => 'Inventory',
                'kitchen' => 'Dapur',
            ];
            $lookup = $aliasMap[strtolower($normalized)] ?? $normalized;

            $role = Role::where('tenant_id', $tenant->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($lookup)])
                ->first();
            if ($role) {
                return $role;
            }
        }

        return Role::where('tenant_id', $tenant->id)
            ->where('name', 'Kasir')
            ->firstOrFail();
    }

    /**
     * Add a user to tenant and assign their initial role.
     */
    public function addMember(Tenant $tenant, array $data): TenantUser
    {
        return DB::transaction(function () use ($tenant, $data) {
            $email = strtolower(trim($data['email']));
            $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
            $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : true;

            if (!$user) {
                $password = !empty($data['password']) ? $data['password'] : 'password123';
                $name = !empty($data['name']) ? trim($data['name']) : explode('@', $email)[0];

                $user = User::create([
                    'id' => (string) Str::ulid(),
                    'name' => $name,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'password' => $password,
                    'is_internal' => false,
                    'is_active' => $isActive,
                ]);
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }
                $user->email = $email;
                if (!empty($data['name'])) {
                    $user->name = trim($data['name']);
                }
                if (array_key_exists('phone', $data)) {
                    $user->phone = $data['phone'];
                }
                if (array_key_exists('job_title', $data)) {
                    $user->job_title = $data['job_title'];
                }
                if (!empty($data['password'])) {
                    $user->password = $data['password'];
                }
                $user->is_active = $isActive;
                $user->save();
            }

            // Ensure membership
            $membership = TenantUser::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'joined_at' => now(),
                ]
            );

            // Resolve and assign role (replace previous role in this tenant so primary role is accurate)
            $role = $this->resolveTenantRole($tenant, $data['role_id'] ?? null, $data['role_name'] ?? null);
            $storeId = $data['store_id'] ?? null;

            UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->delete();

            $this->rbacService->assignRole($user, $role, $tenant->id, $storeId);

            $roles = UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->with(['role.permissions', 'store'])
                ->get();

            $membership->load('user');
            $membership->setRelation('roles', $roles);

            return $membership;
        });
    }

    /**
     * Update user role assignments for a tenant.
     */
    public function updateUserRoles(Tenant $tenant, string $userId, array $roles): void
    {
        DB::transaction(function () use ($tenant, $userId, $roles) {
            $user = User::findOrFail($userId);

            // Verify membership
            $isMember = TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isMember) {
                throw new InvalidArgumentException('User is not a member of this tenant.');
            }

            // Remove existing roles for this tenant
            UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->delete();

            // Assign new roles
            foreach ($roles as $roleItem) {
                $role = Role::findOrFail($roleItem['role_id']);
                $storeId = $roleItem['store_id'] ?? null;

                $this->rbacService->assignRole($user, $role, $tenant->id, $storeId);
            }
        });
    }

    /**
     * Remove user membership and roles from tenant.
     */
    public function removeMember(Tenant $tenant, string $userId): void
    {
        DB::transaction(function () use ($tenant, $userId) {
            // Protect last owner from deletion
            $ownerRole = Role::where('tenant_id', $tenant->id)
                ->where('name', 'Owner')
                ->first();

            if ($ownerRole) {
                $isOwner = UserRole::where('tenant_id', $tenant->id)
                    ->where('user_id', $userId)
                    ->where('role_id', $ownerRole->id)
                    ->exists();

                if ($isOwner) {
                    $otherOwnersCount = UserRole::where('tenant_id', $tenant->id)
                        ->where('role_id', $ownerRole->id)
                        ->where('user_id', '!=', $userId)
                        ->count();

                    if ($otherOwnersCount === 0) {
                        throw new InvalidArgumentException('Cannot remove the only Owner of this tenant.');
                    }
                }
            }

            // Delete user roles in this tenant
            UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->delete();

            // Delete membership
            TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->delete();
        });
    }

    /**
     * Update a tenant team member (user info and roles).
     */
    public function updateMember(Tenant $tenant, string $userId, array $data): TenantUser
    {
        return DB::transaction(function () use ($tenant, $userId, $data) {
            $membership = TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->firstOrFail();

            $user = User::findOrFail($userId);

            if (isset($data['name'])) {
                $user->name = trim($data['name']);
            }
            if (isset($data['email'])) {
                $user->email = strtolower(trim($data['email']));
            }
            if (array_key_exists('phone', $data)) {
                $user->phone = $data['phone'];
            }
            if (array_key_exists('job_title', $data)) {
                $user->job_title = $data['job_title'];
            }
            if (isset($data['is_active'])) {
                $user->is_active = (bool) $data['is_active'];
            }
            if (!empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            // Update role if role_id or role_name provided
            if (!empty($data['role_id']) || !empty($data['role_name'])) {
                $role = $this->resolveTenantRole($tenant, $data['role_id'] ?? null, $data['role_name'] ?? null);
                $this->updateUserRoles($tenant, $userId, [
                    [
                        'role_id' => $role->id,
                        'store_id' => $data['store_id'] ?? null,
                    ]
                ]);
            } elseif (isset($data['roles']) && is_array($data['roles'])) {
                $this->updateUserRoles($tenant, $userId, $data['roles']);
            }

            $roles = UserRole::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->with(['role.permissions', 'store'])
                ->get();

            $membership->load('user');
            $membership->setRelation('roles', $roles);

            return $membership;
        });
    }

    /**
     * Toggle active status of a tenant team member.
     */
    public function toggleMemberStatus(Tenant $tenant, string $userId): User
    {
        $membership = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $user = User::findOrFail($userId);
        $user->is_active = !$user->is_active;
        $user->save();

        return $user;
    }
}
