<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produk_id', 'sku_variant_id', 'gudang_id', 'rak_id', 'jumlah', 'jumlah_minimum'])]
class StokItem extends Model
{
    protected $casts = [
        'jumlah' => 'integer',
        'jumlah_minimum' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }
}
