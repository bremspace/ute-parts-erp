<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8] Karyawan — master data karyawan link ke users.
 */
#[Fillable([
    'user_id', 'nik', 'nama', 'telepon', 'email', 'jabatan', 'cabang_id',
    'tgl_masuk', 'tgl_keluar', 'alasan_keluar', 'catatan_keluar',
    'gaji_pokok', 'status_aktif', 'rekening_bank',
])]
class Karyawan extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'karyawan';

    protected $casts = [
        'tgl_masuk' => 'date',
        'tgl_keluar' => 'date',
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

    public function komponenGaji(): HasMany
    {
        return $this->hasMany(KaryawanKomponenGaji::class);
    }

    public function riwayats(): HasMany
    {
        return $this->hasMany(KaryawanRiwayat::class)->orderBy('tanggal_efektif', 'desc')->orderBy('id', 'desc');
    }

    public function absensiLogs(): HasMany
    {
        return $this->hasMany(AbsensiLog::class);
    }

    public function shiftJadwals(): HasMany
    {
        return $this->hasMany(ShiftJadwal::class);
    }

    public function kpiHasils(): HasMany
    {
        return $this->hasMany(KpiHasil::class);
    }

    /**
     * Scope karyawan aktif.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Scope karyawan non-aktif / diberhentikan.
     */
    public function scopeNonaktif(Builder $query): Builder
    {
        return $query->where('status_aktif', false);
    }

    /**
     * Scope cabang aktif.
     */
    public function scopeCabang(Builder $query, ?int $cabangId): Builder
    {
        if (! $cabangId) {
            return $query;
        }

        return $query->where('cabang_id', $cabangId);
    }

    /**
     * [F1-4] Audit trail data karyawan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id', 'nik', 'nama', 'telepon', 'email', 'jabatan', 'cabang_id',
                'tgl_masuk', 'tgl_keluar', 'alasan_keluar', 'catatan_keluar',
                'gaji_pokok', 'status_aktif',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event) => 'Data Karyawan '.self::labelAksiAktivitas($event));
    }
}
