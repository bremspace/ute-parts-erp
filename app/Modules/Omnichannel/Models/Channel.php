<?php

namespace App\Modules\Omnichannel\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama', 'platform', 'status', 'kredensial', 'last_sync_at', 'last_sync_status', 'is_active',
])]
class Channel extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Channel Omnichannel');
    }

    protected $casts = [
        'kredensial' => 'json', // ponytail: production use encryptedJson + column type text
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function productMappings()
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function orders()
    {
        return $this->hasMany(ChannelOrder::class);
    }
}
