<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_po', 'supplier_id', 'gudang_tujuan_id', 'status', 'metode_bayar',
    'jatuh_tempo', 'total', 'total_dibayar', 'catatan',
])]
class PurchaseOrder extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'purchase_order';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Purchase Order');
    }

    protected $casts = [
        'jatuh_tempo' => 'date',
        'total' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
        'status' => 'string',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranSupplier::class, 'po_id');
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->total_dibayar);
    }
}
