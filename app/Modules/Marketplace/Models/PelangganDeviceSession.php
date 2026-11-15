<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Sesi perangkat aktif milik member (guard `customer`).
 * Dipakai untuk manajemen sesi: daftar perangkat & penghentian sesi jarak jauh.
 */
#[Fillable(['pelanggan_id', 'device_name', 'device_token', 'ip_address', 'user_agent', 'is_active', 'last_activity', 'expires_at'])]
class PelangganDeviceSession extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'pelanggan_device_sessions';

    protected $casts = [
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Sesi Member');
    }
}
