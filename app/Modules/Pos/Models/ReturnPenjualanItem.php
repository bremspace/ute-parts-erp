<?php

namespace App\Modules\Pos\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'return_penjualan_id', 'produk_id', 'sku_variant_id', 'jumlah',
    'harga_satuan', 'subtotal', 'hpp',
])]
class ReturnPenjualanItem extends Model
{
    protected $table = 'return_penjualan_item';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    public function returnPenjualan(): BelongsTo
    {
        return $this->belongsTo(ReturnPenjualan::class);
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
