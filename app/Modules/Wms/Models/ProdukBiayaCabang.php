<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [H9] Biaya persediaan (weighted-average) per produk per cabang.
 * Master Produk.harga_beli tetap menerima weighted-average GLOBAL;
 * baris ini menyimpan biaya cabang yang benar agar tidak ada kontaminasi
 * antar-cabang (IAS 2 ¶25 — weighted average perpetual per lokasi).
 */
class ProdukBiayaCabang extends Model
{
    protected $table = 'produk_biaya_cabang';

    protected $fillable = [
        'produk_id',
        'cabang_id',
        'harga_beli',
    ];

    protected $casts = [
        'produk_id' => 'integer',
        'cabang_id' => 'integer',
        'harga_beli' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}
