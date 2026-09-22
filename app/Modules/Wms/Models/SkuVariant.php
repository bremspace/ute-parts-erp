<?php

namespace App\Modules\Wms\Models;

use App\Modules\Pos\Models\HargaTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'produk_id', 'sku', 'barcode', 'nama_varian', 'satuan_kode', 'atribut', 'harga_beli',
    'harga_jual_retail', 'is_active',
])]
class SkuVariant extends Model
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

    public function hargaTier(): HasMany
    {
        return $this->hasMany(HargaTier::class);
    }
}
