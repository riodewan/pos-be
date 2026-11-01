<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'platform_subscription_plans';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'price',
        'billing_cycle',
        'max_outlets',
        'max_staff',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'max_outlets' => 'integer',
            'max_staff' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
