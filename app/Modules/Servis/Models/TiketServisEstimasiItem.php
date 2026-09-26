<?php

namespace App\Modules\Servis\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id', 'tipe', 'jenis_servis_id', 'produk_id', 'sku_variant_id',
    'nama_item', 'qty', 'harga', 'subtotal',
])]
class TiketServisEstimasiItem extends Model
{
    protected $table = 'tiket_servis_estimasi_item';

    protected $casts = [
        'qty' => 'integer',
        'harga' => 'float',
        'subtotal' => 'float',
    ];

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function jenisServis(): BelongsTo
    {
        return $this->belongsTo(JenisServis::class);
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
