<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'units';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'type',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'unit_id');
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'unit_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'unit_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'unit_id');
    }

    public function wastageItems(): HasMany
    {
        return $this->hasMany(WastageItem::class, 'unit_id');
    }
}
