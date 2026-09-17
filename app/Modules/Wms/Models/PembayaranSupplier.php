<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['po_id', 'jumlah', 'dibayar_at', 'user_id', 'keterangan'])]
class PembayaranSupplier extends Model
{
    protected $table = 'pembayaran_supplier';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'dibayar_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}