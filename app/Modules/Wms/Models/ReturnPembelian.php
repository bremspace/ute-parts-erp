<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Retur pembelian (return ke supplier) — tabel return_pembelian (migrasi SID fase 1).
 * [F1-1] Observer ReturnPembelianObserver auto-fire approval bila jumlah > threshold.
 */
#[Fillable([
    'no_return', 'purchase_order_id', 'supplier_id', 'tanggal', 'jumlah', 'status', 'alasan', 'is_migrasi_sid',
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
        'jumlah' => 'decimal:2',
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
}
