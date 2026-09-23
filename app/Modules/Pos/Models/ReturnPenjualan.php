<?php

namespace App\Modules\Pos\Models;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retur penjualan (customer return) — tabel return_penjualan (migrasi SID fase 1).
 * [F1-1] Observer ReturnPenjualanObserver auto-fire approval bila jumlah > threshold.
 */
#[Fillable([
    'no_return', 'transaksi_id', 'pelanggan_id', 'tanggal', 'jumlah', 'status', 'alasan', 'is_migrasi_sid',
])]
class ReturnPenjualan extends Model
{
    protected $table = 'return_penjualan';

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'decimal:2',
        'is_migrasi_sid' => 'boolean',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }
}
