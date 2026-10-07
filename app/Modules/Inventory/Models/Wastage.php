<?php

namespace App\Modules\Inventory\Models;

use App\Core\Enums\WastageStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Tenant\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wastage extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'wastages';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'wastage_number',
        'wastage_date',
        'reason',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'wastage_date' => 'date',
            'status' => WastageStatus::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wastageItems(): HasMany
    {
        return $this->hasMany(WastageItem::class, 'wastage_id');
    }
}
