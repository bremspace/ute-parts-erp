<?php

namespace App\Modules\Hr\Models;

use App\Modules\Servis\Models\TiketServis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['slip_id', 'tiket_servis_id', 'teknisi_id', 'jenis', 'nominal'])]
class PayrollKomisiDetail extends Model
{
    use LogsActivity;

    protected $table = 'payroll_komisi_detail';

    protected $casts = ['nominal' => 'decimal:2'];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class);
    }

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
