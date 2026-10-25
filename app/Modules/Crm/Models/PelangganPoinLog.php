<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Pos\Models\Transaksi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pelanggan_id',
    'transaksi_id',
    'tipe',
    'poin',
    'saldo_sebelum',
    'saldo_sesudah',
    'keterangan',
    'created_by',
])]
class PelangganPoinLog extends Model
{
    public const TIPE_EARNED = 'earned';

    public const TIPE_REDEEMED = 'redeemed';

    public const TIPE_REFUND = 'refund';

    public const TIPE_PENYESUAIAN = 'penyesuaian';

    protected $table = 'pelanggan_poin_log';

    protected $casts = [
        'poin' => 'integer',
        'saldo_sebelum' => 'integer',
        'saldo_sesudah' => 'integer',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
