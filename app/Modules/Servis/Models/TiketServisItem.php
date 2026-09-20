<?php

namespace App\Modules\Servis\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id', 'tipe', 'produk_id', 'sku_variant_id',
    'nama_item', 'qty', 'harga', 'hpp'
])]
class TiketServisItem extends Model
{
    protected $table = 'tiket_servis_item';

    protected $casts = [
        'qty' => 'integer',
        'harga' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
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