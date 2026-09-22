<?php

namespace App\Modules\Omnichannel\Models;

use App\Modules\Pos\Models\Transaksi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id', 'channel_order_id', 'transaksi_id', 'payload',
    'channel_status', 'status', 'catatan', 'estimasi_biaya_platform',
])]
class ChannelOrder extends Model
{
    protected $table = 'channel_orders';

    protected $casts = [
        'payload' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }
}
