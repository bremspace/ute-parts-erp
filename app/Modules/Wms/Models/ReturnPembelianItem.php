<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'return_pembelian_id', 'produk_id', 'sku_variant_id', 'jumlah',
    'harga_beli', 'subtotal',
])]
class ReturnPembelianItem extends Model
{
    protected $table = 'return_pembelian_item';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'harga_beli' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function returnPembelian(): BelongsTo
    {
        return $this->belongsTo(ReturnPembelian::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }
}
