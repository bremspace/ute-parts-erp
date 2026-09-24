<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'periode', 'tanggal_mulai', 'tanggal_selesai', 'status', 'catatan',
])]
class PayrollPeriode extends Model
{
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBAYAR = 'dibayar';

    protected $table = 'payroll_periode';

    protected $casts = ['status' => 'string'];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
