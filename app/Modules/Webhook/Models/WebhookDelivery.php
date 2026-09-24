<?php

namespace App\Modules\Webhook\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [F3-5] Log pengiriman webhook (ringan — hash + rowid context, TANPA payload penuh).
 * Status: pending → delivered | retry (antre ulang) | failed (permanen).
 */
#[Fillable([
    'webhook_endpoint_id', 'event', 'payload_hash', 'context',
    'status', 'attempt', 'response_code', 'error', 'delivered_at',
])]
class WebhookDelivery extends Model
{
    protected $table = 'webhook_delivery';

    protected $casts = [
        'context' => 'array',
        'attempt' => 'integer',
        'response_code' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
