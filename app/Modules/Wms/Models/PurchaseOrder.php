<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'no_po', 'supplier_id', 'gudang_tujuan_id', 'status', 'metode_bayar',
    'jatuh_tempo', 'total', 'total_dibayar', 'catatan',
])]
class PurchaseOrder extends Model
{
    protected $table = 'purchase_order';

    protected $casts = [
        'jatuh_tempo' => 'date',
        'total' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
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
