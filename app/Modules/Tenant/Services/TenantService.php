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
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'ACTIVE',
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
            $owner = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $data['owner_name'],
                    'password' => $data['password'],
                    'is_internal' => false,
                    'is_active' => true,
                ]
            );

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
     * Add a user to tenant and assign their initial role.
     */
    public function addMember(Tenant $tenant, array $data): TenantUser
    {
        return DB::transaction(function () use ($tenant, $data) {
            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                $password = $data['password'] ?? Str::random(12);
                $name = $data['name'] ?? explode('@', $data['email'])[0];

                $user = User::create([
                    'id' => (string) Str::ulid(),
                    'name' => $name,
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'is_internal' => false,
                    'is_active' => true,
                ]);
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

            // Assign initial role
            $role = Role::findOrFail($data['role_id']);
            $storeId = $data['store_id'] ?? null;

            $this->rbacService->assignRole($user, $role, $tenant->id, $storeId);

            return $membership->load('user');
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
}
