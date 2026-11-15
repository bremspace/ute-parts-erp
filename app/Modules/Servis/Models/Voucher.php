<?php

namespace App\Modules\Servis\Models;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kode', 'pelanggan_id', 'tiket_servis_id', 'amount', 'sisa', 'status',
    'expired_at', 'used_at', 'used_tiket_id', 'catatan',
])]
class Voucher extends Model
{
    protected $casts = [
        'amount' => 'decimal:2',
        'sisa' => 'decimal:2',
        'expired_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function tiketAsal(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class, 'tiket_servis_id');
    }

    public function tiketPemakai(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class, 'used_tiket_id');
    }

    public function getAktifAttribute(): bool
    {
        return $this->status === 'aktif'
            && (float) $this->sisa > 0
            && ($this->expired_at === null || now()->lessThan($this->expired_at));
    }
}
