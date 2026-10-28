<?php

namespace App\Modules\Reporting\Models;

use App\Core\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    use HasUlids, BelongsToStore;

    protected $table = 'disbursements';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'batch_id',
        'bank_name',
        'account_number',
        'gross_amount',
        'fee',
        'net_received',
        'status',
        'requested_by',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'float',
            'fee' => 'float',
            'net_received' => 'float',
            'requested_at' => 'datetime',
        ];
    }
}
