<?php

namespace App\Modules\PosOrder\Models;

use App\Core\Enums\QrStatus;
use App\Modules\Tenant\Models\Store;
use App\Core\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrCode extends Model
{
    use HasFactory, HasUlids, SoftDeletes, BelongsToStore;

    protected $table = 'qr_codes';

    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_TABLE = 'TABLE';
    public const TYPE_DISPOSABLE = 'DISPOSABLE';

    protected $fillable = [
        'store_id',
        'table_id',
        'token',
        'type',
        'label',
        'status',
        'expires_at',
        'max_uses',
        'used_count',
    ];

    protected $appends = [
        'is_active',
        'effective_status',
    ];

    protected function casts(): array
    {
        return [
            'status' => QrStatus::class,
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'used_count' => 'integer',
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === QrStatus::ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->max_uses !== null && (int) $this->used_count >= (int) $this->max_uses;
    }

    /**
     * Status efektif untuk ditampilkan: ACTIVE | INACTIVE | EXPIRED | USED_UP.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status !== QrStatus::ACTIVE) {
            return $this->status?->value ?? 'INACTIVE';
        }
        if ($this->isExpired()) {
            return QrStatus::EXPIRED->value;
        }
        if ($this->isUsedUp()) {
            return 'USED_UP';
        }

        return QrStatus::ACTIVE->value;
    }

    /**
     * Mengembalikan pesan penolakan jika QR tidak bisa dipakai untuk memesan, atau null jika valid.
     */
    public function orderingBlockReason(): ?string
    {
        if ($this->isExpired()) {
            return 'QR code ini sudah kedaluwarsa dan tidak dapat digunakan lagi.';
        }
        if ($this->isUsedUp()) {
            return 'QR code sekali pakai ini sudah digunakan. Silakan minta QR baru ke kasir.';
        }

        return null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }
}
