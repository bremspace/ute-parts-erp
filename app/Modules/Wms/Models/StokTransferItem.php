<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stok_transfer_id', 'produk_id', 'sku_variant_id', 'rak_id', 'jumlah',
])]
class StokTransferItem extends Model
{
    protected $table = 'stok_transfer_item';

    protected $casts = [
        'jumlah' => 'integer',
    ];

    public function stokTransfer(): BelongsTo
    {
        return $this->belongsTo(StokTransfer::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }
}
