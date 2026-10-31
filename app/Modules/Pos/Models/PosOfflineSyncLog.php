<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id',
    'user_id',
    'batch_id',
    'total_payload',
    'total_success',
    'total_conflicts',
    'conflict_details',
    'ip_address',
    'user_agent',
])]
class PosOfflineSyncLog extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'pos_offline_sync_logs';

    protected $casts = [
        'total_payload' => 'integer',
        'total_success' => 'integer',
        'total_conflicts' => 'integer',
        'conflict_details' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('PosOfflineSyncLog');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
