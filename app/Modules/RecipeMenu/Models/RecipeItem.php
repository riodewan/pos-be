<?php

namespace App\Modules\RecipeMenu\Models;

use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'recipe_items';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'unit_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
