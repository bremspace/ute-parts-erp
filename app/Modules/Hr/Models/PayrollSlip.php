<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'payroll_periode_id', 'karyawan_id', 'gaji_pokok', 'total_tunjangan',
    'total_potongan', 'total_komisi', 'total_gaji', 'rincian', 'jurnal_id',
])]
class PayrollSlip extends Model
{
    use LogsActivity;

    protected $table = 'payroll_slip';

    protected $casts = [
        'gaji_pokok' => 'decimal:2', 'total_tunjangan' => 'decimal:2',
        'total_potongan' => 'decimal:2', 'total_komisi' => 'decimal:2',
        'total_gaji' => 'decimal:2', 'rincian' => 'array',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
