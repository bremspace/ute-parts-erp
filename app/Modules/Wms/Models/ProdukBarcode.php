<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukBarcode extends Model
{
    protected $table = 'produk_barcodes';

    protected $fillable = [
        'produk_id',
        'sku',
        'barcode',
        'label',
        'urutan',
        'is_utama',
    ];

    protected $casts = [
        'is_utama' => 'boolean',
        'urutan' => 'integer',
        'produk_id' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
