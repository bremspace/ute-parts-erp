<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Pos\Models\Transaksi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaksi_id', 'kurir', 'layanan', 'ongkir', 'asal_cabang',
    'nama_penerima', 'alamat_tujuan', 'telepon_tujuan', 'tracking_id', 'status',
])]
class Pengiriman extends Model
{
    protected $table = 'pengiriman';

    protected $casts = [
        'ongkir' => 'decimal:2',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }
}
