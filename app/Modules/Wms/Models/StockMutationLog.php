<?php

namespace App\Modules\Wms\Models;

use App\Modules\Wms\Models\Produk;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'produk_id', 'sku_variant_id', 'gudang_id', 'delta', 'sumber',
    'referensi_tipe', 'referensi_id', 'terjadi_at'
])]
class StockMutationLog extends Model
{
    protected $table = 'stock_mutation_log';

    protected $casts = [
        'delta' => 'integer',
        'terjadi_at' => 'datetime',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}