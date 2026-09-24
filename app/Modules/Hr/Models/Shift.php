<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8b] Shift kerja — bisa lintas hari (jam_selesai < jam_mulai = overnight).
 */
#[Fillable([
    'cabang_id', 'nama', 'jam_mulai', 'jam_selesai', 'is_aktif',
])]
class Shift extends Model
{
    use LogsActivity;

    protected $table = 'shift';

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(ShiftJadwal::class);
    }

    /**
     * Shift lintas hari: jam_selesai < jam_mulai (mis. Malam 22:00-06:00).
     */
    public function apakahOvernight(): bool
    {
        return $this->jam_selesai < $this->jam_mulai;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
