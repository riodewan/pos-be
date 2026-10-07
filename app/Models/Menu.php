<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'menus';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'status',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'menu_items', 'menu_id', 'product_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
