<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformMasterItem extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'platform_master_items';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'group',
        'name',
        'code',
        'icon',
        'description',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
