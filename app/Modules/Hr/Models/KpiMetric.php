<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8b] KPI Metric — rumus wajib whitelist kode (BUKAN eval bebas).
 * Whitelist: tiket_selesai, transaksi_kasir, selisih_kas, lead_won, penjualan_lead_won.
 */
#[Fillable([
    'kode', 'nama', 'rumus', 'target', 'satuan', 'periode', 'is_aktif',
])]
class KpiMetric extends Model
{
    use LogsActivity;

    protected $table = 'kpi_metric';

    protected $casts = [
        'target' => 'decimal:2',
        'is_aktif' => 'boolean',
    ];

    public function hasil(): HasMany
    {
        return $this->hasMany(KpiHasil::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
