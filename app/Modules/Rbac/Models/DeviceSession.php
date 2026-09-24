<?php

namespace App\Modules\Rbac\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['user_id', 'device_name', 'device_token', 'ip_address', 'user_agent', 'is_active', 'last_activity', 'expires_at'])]
class DeviceSession extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'device_sessions';

    protected $casts = [
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Device Session');
    }
}
