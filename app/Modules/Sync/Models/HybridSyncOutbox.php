<?php

namespace App\Modules\Sync\Models;

use Illuminate\Database\Eloquent\Model;

class HybridSyncOutbox extends Model
{
    protected $table = 'hybrid_sync_outbox';

    protected $fillable = [
        'sync_uuid',
        'event_type',
        'entity_type',
        'entity_id',
        'cabang_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}
