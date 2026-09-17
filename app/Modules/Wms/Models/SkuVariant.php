<?php

namespace App\Modules\Wms\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'produk_id', 'sku', 'nama_varian', 'atribut', 'harga_beli',
    'harga_jual_retail', 'is_active'
])]
class SkuVariant extends \Illuminate\Database\Eloquent\Model
{
    protected $casts = [
        'is_active' => 'boolean',
        'atribut' => 'array',
        'harga_beli' => 'decimal:2',
        'harga_jual_retail' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function stokItems(): HasMany
    {
        return $this->hasMany(StokItem::class);
    }
}
