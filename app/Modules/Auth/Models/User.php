<?php

namespace App\Modules\Auth\Models;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\Wastage;
use App\Modules\PosOrder\Models\Order;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\System\Models\AuditLog;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_internal',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_internal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'user_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users', 'user_id', 'tenant_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'created_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'created_by');
    }

    public function wastages(): HasMany
    {
        return $this->hasMany(Wastage::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Check if user is an internal platform administrator.
     */
    public function isInternalAdmin(): bool
    {
        if ($this->is_internal) {
            return true;
        }

        return $this->userRoles()
            ->whereHas('role', fn ($q) => $q->where('scope', 'INTERNAL'))
            ->exists();
    }

    /**
     * Check if user belongs to a specific tenant.
     */
    public function belongsToTenant(string $tenantId): bool
    {
        if ($this->isInternalAdmin()) {
            return true;
        }

        return $this->tenantUsers()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Check if user has access to a specific store within a tenant.
     */
    public function hasAccessToStore(string $storeId, string $tenantId): bool
    {
        if ($this->isInternalAdmin()) {
            return true;
        }

        if (!$this->belongsToTenant($tenantId)) {
            return false;
        }

        $roles = $this->userRoles()->where('tenant_id', $tenantId)->get();

        if ($roles->isEmpty()) {
            // If user is a tenant member with no restricted store roles, allow
            return true;
        }

        return $roles->contains(function ($role) use ($storeId) {
            return is_null($role->store_id) || $role->store_id === $storeId;
        });
    }

    /**
     * Check if user has a specific permission in active or specified context.
     */
    public function hasPermission(string $permission, ?string $tenantId = null, ?string $storeId = null): bool
    {
        return app(\App\Modules\Rbac\Services\RbacService::class)
            ->userHasPermission($this, $permission, $tenantId, $storeId);
    }

    /**
     * Check if user has a specific role in active or specified context.
     */
    public function hasRole(string|array $role, ?string $tenantId = null, ?string $storeId = null): bool
    {
        return app(\App\Modules\Rbac\Services\RbacService::class)
            ->userHasRole($this, $role, $tenantId, $storeId);
    }

    /**
     * Get all permission names for the user in active or specified context.
     */
    public function getPermissions(?string $tenantId = null, ?string $storeId = null): array
    {
        return app(\App\Modules\Rbac\Services\RbacService::class)
            ->getUserPermissions($this, $tenantId, $storeId);
    }
}
