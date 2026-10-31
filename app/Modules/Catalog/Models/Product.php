<?php

namespace App\Modules\Catalog\Models;

use App\Core\Enums\CommonStatus;
use App\Modules\PosOrder\Models\OrderItem;
use App\Modules\RecipeMenu\Models\MenuItem;
use App\Modules\RecipeMenu\Models\Recipe;
use App\Modules\Tenant\Models\Store;
use App\Core\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes, BelongsToStore;

    protected $table = 'products';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'name',
        'sku',
        'description',
        'image_url',
        'selling_price',
        'status',
        'category',
        'type',
        'cost_price',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'status' => CommonStatus::class,
        ];
    }

    public function getPriceAttribute(): ?string
    {
        return $this->selling_price;
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['selling_price'] = $value;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }
}
