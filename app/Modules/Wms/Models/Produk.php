<?php

namespace App\Modules\Wms\Models;

use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\HargaTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nama', 'slug', 'barcode', 'deskripsi', 'kategori', 'brand_kompatibel',
    'model_kompatibel', 'kondisi', 'satuan', 'harga_beli',
    'harga_jual_retail', 'gambar', 'foto', 'meta_title', 'meta_description',
    'kompatibilitas_hp', 'is_active',
])]
class Produk extends Model
{
    protected $table = 'produk';

    protected $casts = [
        'is_active' => 'boolean',
        'harga_beli' => 'decimal:2',
        'harga_jual_retail' => 'decimal:2',
        'foto' => 'array', // [T-11]
        'kompatibilitas_hp' => 'array', // [T-11] terstruktur [{merk, model}]
    ];

    public function skuVariants(): HasMany
    {
        return $this->hasMany(SkuVariant::class)->orderBy('is_active', 'desc');
    }

    public function channelMappings(): HasMany
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
