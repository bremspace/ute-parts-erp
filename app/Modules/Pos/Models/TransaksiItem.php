<?php

namespace App\Modules\Pos\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaksi_id', 'produk_id', 'sku_variant_id', 'jumlah',
    'satuan', 'konversi', 'harga_satuan', 'diskon_nominal', 'subtotal', 'hpp',
    'catatan',
])]
class TransaksiItem extends Model
{
    /**
     * @property int $transaksi_id
     * @property int $produk_id
     * @property int|null $sku_variant_id
     * @property int $jumlah
     * @property string $satuan
     * @property float $konversi
     * @property float $harga_satuan
     * @property float $diskon_nominal
     * @property float $subtotal
     * @property float $hpp
     * @property string|null $catatan
     */
    protected $table = 'transaksi_item';

    protected $casts = [
        'jumlah' => 'integer',
        'konversi' => 'decimal:4',
        'harga_satuan' => 'decimal:2',
        'diskon_nominal' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    /**
     * @return BelongsTo<SkuVariant, $this>
     */
    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }
}
