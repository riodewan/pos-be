<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Ingredient;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'wastage_items';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'wastage_id',
        'ingredient_id',
        'quantity',
        'unit_id',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function wastage(): BelongsTo
    {
        return $this->belongsTo(Wastage::class, 'wastage_id');
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
