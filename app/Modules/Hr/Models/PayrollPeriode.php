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
