<?php

namespace App\Modules\Wms\Models;

use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Pos\Models\HargaTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nama', 'slug', 'deskripsi', 'kategori', 'brand_kompatibel',
    'model_kompatibel', 'kondisi', 'satuan', 'harga_beli',
    'harga_jual_retail', 'gambar', 'is_active'
])]
class Produk extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'produk';

    protected $casts = [
        'is_active' => 'boolean',
        'harga_beli' => 'decimal:2',
        'harga_jual_retail' => 'decimal:2',
    ];

    public function skuVariants(): HasMany
    {
        return $this->hasMany(SkuVariant::class);
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
