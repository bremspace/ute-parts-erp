<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['karyawan_id', 'tipe', 'nama', 'nominal_bulanan', 'is_aktif']])
class KaryawanKomponenGaji extends Model
{
    use LogsActivity;

    protected $table = 'karyawan_komponen_gaji';

    protected $casts = ['nominal_bulanan' => 'decimal:2', 'is_aktif' => 'boolean'];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
