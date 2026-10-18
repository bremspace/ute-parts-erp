<?php

namespace App\Modules\Pos\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'return_penjualan_id', 'produk_id', 'sku_variant_id', 'jumlah',
    'harga_satuan', 'subtotal', 'hpp', 'kondisi_barang', 'nomor_seri',
    'supplier_id', 'status_karantina', 'return_pembelian_id',
])]
class ReturnPenjualanItem extends Model
{
    protected $table = 'return_penjualan_item';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hpp' => 'decimal:2',
        'nomor_seri' => 'array',
    ];

    public function returnPenjualan(): BelongsTo
    {
        return $this->belongsTo(ReturnPenjualan::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function returnPembelian(): BelongsTo
    {
        return $this->belongsTo(ReturnPembelian::class);
    }
}
