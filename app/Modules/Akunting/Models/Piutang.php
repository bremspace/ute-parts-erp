<?php

namespace App\Modules\Akunting\Models;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'no_piutang', 'pelanggan_id', 'transaksi_id', 'cabang_id', 'jumlah',
    'jumlah_dibayar', 'jatuh_tempo', 'status', 'keterangan',
])]
class Piutang extends Model
{
    protected $table = 'piutang';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_dibayar' => 'decimal:2',
        'jatuh_tempo' => 'date',
    ];

    protected static function booted(): void
    {
        // Jaga kolom `sisa` konsisten di DB (dipakai query raw/agregasi) meski
        // create/update dari mana pun (POS, controller, Livewire) — accessor tetap sumber kebenaran utk baca.
        static::saving(function (self $piutang) {
            $piutang->sisa = max(0, (float) $piutang->jumlah - (float) $piutang->jumlah_dibayar);
        });
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->jumlah - (float) $this->jumlah_dibayar);
    }

    public function getJatuhTempoLewatAttribute(): bool
    {
        return $this->status !== 'lunas' && $this->jatuh_tempo && $this->jatuh_tempo->lt(now()->toDateString());
    }
}
