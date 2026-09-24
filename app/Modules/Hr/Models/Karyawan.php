<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8] Karyawan — master data karyawan link ke users.
 */
#[Fillable([
    'user_id', 'nik', 'nama', 'jabatan', 'cabang_id', 'tgl_masuk',
    'gaji_pokok', 'status_aktif', 'rekening_bank',
])]
class Karyawan extends Model
{
    use LogsActivity;

    protected $table = 'karyawan';

    protected $casts = [
        'tgl_masuk' => 'date',
        'gaji_pokok' => 'decimal:2',
        'status_aktif' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
