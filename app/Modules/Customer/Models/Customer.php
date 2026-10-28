<?php

namespace App\Modules\Customer\Models;

use App\Core\Traits\BelongsToTenant;
use App\Modules\PosOrder\Models\Order;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUlids, SoftDeletes, BelongsToTenant;

    protected $table = 'customers';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'tier',
        'points',
        'total_spent',
        'total_visits',
        'last_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'total_spent' => 'decimal:2',
            'total_visits' => 'integer',
            'last_visit_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
