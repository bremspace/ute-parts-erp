<?php

namespace App\Modules\Pos\Models;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Retur penjualan (customer return) — tabel return_penjualan (migrasi SID fase 1).
 * [F1-1] Observer ReturnPenjualanObserver auto-fire approval bila jumlah > threshold.
 */
#[Fillable([
    'no_return', 'transaksi_id', 'pelanggan_id', 'tanggal', 'jumlah', 'status', 'alasan', 'is_migrasi_sid',
])]
class ReturnPenjualan extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'return_penjualan';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Return Penjualan');
    }

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
