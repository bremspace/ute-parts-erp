<?php

namespace App\Modules\Wms\Models;

use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama', 'slug', 'barcode', 'deskripsi', 'kategori', 'brand_kompatibel',
    'model_kompatibel', 'kondisi', 'satuan', 'harga_beli',
    'harga_jual_retail', 'gambar', 'foto', 'meta_title', 'meta_description',
    'kompatibilitas_hp', 'is_active',
    // [T-44]
    'brand_id', 'kualitas_id',
    // [HARGA FLEKSIBEL]
    'harga_fleksibel',
    // [F2-3] Serial number tracking
    'sn',
])]
class Produk extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'produk';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Produk');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'sn' => 'boolean', // [F2-3] wajib SN di GRN/POS/servis
        'harga_beli' => 'decimal:2',
        'harga_jual_retail' => 'decimal:2',
        'foto' => 'array', // [T-11]
        'kompatibilitas_hp' => 'array', // [T-11] terstruktur [{merk, model}]
    ];

    public function skuVariants(): HasMany
    {
        return $this->hasMany(SkuVariant::class)->orderBy('is_active', 'desc');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function kualitas(): BelongsTo
    {
        return $this->belongsTo(KualitasProduk::class, 'kualitas_id');
    }

    public function tipeHps(): BelongsToMany
    {
        return $this->belongsToMany(
            TipeHp::class,
            'kompatibilitas_produk_tipe_hp',
            'produk_id',
            'tipe_hp_id'
        );
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

    public function nomorSeris(): HasMany
    {
        return $this->hasMany(NomorSeri::class);
    }
}
