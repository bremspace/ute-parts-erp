<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gudang_id', 'produk_id', 'sku_variant_id', 'user_id',
    'jenis', 'referensi_tipe', 'referensi_id',
    'jumlah_sebelum', 'perubahan', 'jumlah_setelah', 'catatan',
])]
class StokLog extends Model
{
    protected $table = 'stok_log';

    protected $casts = [
        'jumlah_sebelum' => 'integer',
        'perubahan' => 'integer',
        'jumlah_setelah' => 'integer',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
