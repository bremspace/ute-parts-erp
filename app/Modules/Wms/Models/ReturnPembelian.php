<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Retur pembelian (return ke supplier) — tabel return_pembelian.
 * [F1-1] Observer ReturnPembelianObserver auto-fire approval bila jumlah > threshold.
 */
#[Fillable([
    'no_return', 'purchase_order_id', 'supplier_id', 'cabang_id', 'gudang_id', 'user_id',
    'tanggal', 'jumlah', 'nilai_realisasi', 'selisih_klaim', 'status', 'metode_pengembalian',
    'kebijakan_retur', 'alasan', 'tanggal_kirim', 'ekspedisi', 'no_resi', 'penerima_vendor',
    'tanggal_terima_vendor', 'tanggal_selesai', 'catatan_penyelesaian', 'unit_pengganti_detail', 'is_migrasi_sid',
])]
class ReturnPembelian extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'return_pembelian';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Return Pembelian');
    }

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_kirim' => 'datetime',
        'tanggal_terima_vendor' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'jumlah' => 'decimal:2',
        'nilai_realisasi' => 'decimal:2',
        'selisih_klaim' => 'decimal:2',
        'unit_pengganti_detail' => 'array',
        'is_migrasi_sid' => 'boolean',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(ReturnPembelianItem::class, 'return_pembelian_id');
    }
}
