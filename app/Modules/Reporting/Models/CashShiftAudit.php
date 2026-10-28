<?php

namespace App\Modules\Reporting\Models;

use App\Core\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CashShiftAudit extends Model
{
    use HasUlids, BelongsToStore;

    protected $table = 'cash_shift_audits';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'cashier_id',
        'shift_name',
        'shift_date',
        'opening_cash',
        'actual_cash',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'opening_cash' => 'float',
            'actual_cash' => 'float',
        ];
    }
}
