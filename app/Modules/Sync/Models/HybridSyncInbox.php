<?php

namespace App\Modules\Sync\Models;

use Illuminate\Database\Eloquent\Model;

class HybridSyncInbox extends Model
{
    protected $table = 'hybrid_sync_inbox';

    protected $fillable = [
        'sync_uuid',
        'source_node',
        'event_type',
        'payload_hash',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
