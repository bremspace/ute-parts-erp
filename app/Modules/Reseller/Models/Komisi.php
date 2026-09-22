<?php

namespace App\Modules\Reseller\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'no_komisi', 'pelanggan_id', 'transaksi_id', 'skema_komisi_id',
    'jumlah_transaksi', 'nominal_komisi', 'status', 'approved_by_id', 'approved_at', 'keterangan',
])]
class Komisi extends Model
{
    protected $table = 'komisi';

    protected $casts = [
        'jumlah_transaksi' => 'decimal:2',
        'nominal_komisi' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function skemaKomisi(): BelongsTo
    {
        return $this->belongsTo(SkemaKomisi::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
