<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id', 'produk_id', 'sku_variant_id', 'satuan', 'konversi',
    'harga_beli', 'jumlah', 'jumlah_dasar', 'subtotal',
])]
class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_item';

    protected $casts = [
        'harga_beli' => 'decimal:2',
        'jumlah' => 'decimal:2',
        'konversi' => 'decimal:4',
        'jumlah_dasar' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            if (empty($item->konversi) || (float) $item->konversi <= 0) {
                $item->konversi = 1;
            }
            if (empty($item->jumlah_dasar) || (float) $item->jumlah_dasar <= 0) {
                $item->jumlah_dasar = round(((float) $item->jumlah) * ((float) $item->konversi), 2);
            }
        });
    }
}
