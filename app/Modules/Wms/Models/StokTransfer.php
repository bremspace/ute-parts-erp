<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'no_transfer', 'gudang_asal_id', 'gudang_tujuan_id', 'user_pengirim_id',
    'user_penerima_id', 'status', 'tanggal_kirim', 'tanggal_terima', 'catatan'
])]
class StokTransfer extends Model
{
    protected $table = 'stok_transfer';

    protected $casts = [
        'tanggal_kirim' => 'datetime',
        'tanggal_terima' => 'datetime',
    ];

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_pengirim_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_penerima_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StokTransferItem::class);
    }
}
