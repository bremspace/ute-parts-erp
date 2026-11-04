<?php

namespace App\Modules\Omnichannel\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id',
    'channel_order_id',
    'event',
    'payload',
    'status',
    'error',
    'processed_at',
])]
class ChannelWebhookInbound extends Model
{
    protected $table = 'channel_webhook_inbound';

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
