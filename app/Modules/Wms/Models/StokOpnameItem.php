<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stok_opname_id', 'produk_id', 'sku_variant_id',
    'stok_sistem', 'stok_fisik', 'selisih', 'catatan'
])]
class StokOpnameItem extends Model
{
    protected $table = 'stok_opname_item';

    protected $casts = [
        'stok_sistem' => 'integer',
        'stok_fisik' => 'integer',
        'selisih' => 'integer',
    ];

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class);
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
