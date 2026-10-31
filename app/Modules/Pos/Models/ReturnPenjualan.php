<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Retur penjualan (customer return) — tabel return_penjualan.
 * [F1-1] Observer ReturnPenjualanObserver auto-fire approval bila jumlah > threshold.
 */
#[Fillable([
    'no_return', 'transaksi_id', 'pelanggan_id', 'cabang_id', 'gudang_id', 'user_id',
    'tanggal', 'jumlah', 'status', 'metode_pengembalian', 'alasan', 'is_migrasi_sid',
    'tipe_penyelesaian', 'tukar_transaksi_id', 'selisih_nominal', 'tukar_items_payload', 'foto_bukti', 'catatan_qc', 'channel',
    'metode_bayar_selisih', 'jumlah_bayar_selisih', 'kembalian_selisih',
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
        'selisih_nominal' => 'decimal:2',
        'jumlah_bayar_selisih' => 'decimal:2',
        'kembalian_selisih' => 'decimal:2',
        'tukar_items_payload' => 'array',
        'foto_bukti' => 'array',
        'is_migrasi_sid' => 'boolean',
    ];

    public function tukarTransaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'tukar_transaksi_id');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnPenjualanItem::class, 'return_penjualan_id');
    }
}
