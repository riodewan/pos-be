<?php

namespace App\Modules\Tenant\Models;

use App\Core\Enums\TenantStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\RecipeMenu\Models\Menu;
use App\Modules\System\Models\AuditLog;
use App\Modules\System\Models\Setting;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tenants';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'status',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'onboarded_at' => 'datetime',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'tenant_id');
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'user_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'tenant_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'tenant_id');
    }

    public function products(): HasManyThrough
    {
        return $this->hasManyThrough(Product::class, Store::class, 'tenant_id', 'store_id');
    }

    public function ingredients(): HasManyThrough
    {
        return $this->hasManyThrough(Ingredient::class, Store::class, 'tenant_id', 'store_id');
    }

    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(Supplier::class, Store::class, 'tenant_id', 'store_id');
    }

    public function menus(): HasManyThrough
    {
        return $this->hasManyThrough(Menu::class, Store::class, 'tenant_id', 'store_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'tenant_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'tenant_id');
    }
}
