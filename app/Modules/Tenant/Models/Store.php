<?php

namespace App\Modules\Tenant\Models;

use App\Core\Enums\CommonStatus;
use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\Wastage;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\RecipeMenu\Models\Menu;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\System\Models\AuditLog;
use App\Modules\System\Models\Setting;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'stores';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'phone',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommonStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'store_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'store_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'store_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'store_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'store_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'store_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'store_id');
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'store_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'store_id');
    }

    public function wastages(): HasMany
    {
        return $this->hasMany(Wastage::class, 'store_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class, 'store_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class, 'store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'store_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'store_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'store_id');
    }
}
