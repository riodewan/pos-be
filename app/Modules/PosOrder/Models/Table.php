<?php

namespace App\Modules\PosOrder\Models;

use App\Core\Enums\TableStatus;
use App\Modules\Tenant\Models\Store;
use App\Core\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, HasUlids, SoftDeletes, BelongsToStore;

    protected $table = 'tables';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'capacity',
        'status',
        'area',
        'category',
        'table_number',
        'operational_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'table_number' => 'integer',
            'status' => TableStatus::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class, 'table_id');
    }

    public function activeQrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'table_id')->where('status', 'ACTIVE');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
