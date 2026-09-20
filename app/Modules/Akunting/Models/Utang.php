<?php

namespace App\Modules\Akunting\Models;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'no_utang', 'referensi_tipe', 'referensi_id', 'pelanggan_id',
    'kreditor_nama', 'jumlah', 'jumlah_dibayar', 'jatuh_tempo', 'status', 'keterangan',
])]
class Utang extends Model
{
    protected $table = 'utang';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_dibayar' => 'decimal:2',
        'jatuh_tempo' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->jumlah - (float) $this->jumlah_dibayar);
    }
}
