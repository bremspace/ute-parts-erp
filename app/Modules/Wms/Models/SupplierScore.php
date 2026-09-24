<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-2 / G-11] Supplier Scoring — on-time %, quality return %, avg harga.
 * Tampil di form PO.
 */
#[Fillable([
    'supplier_id', 'periode', 'on_time_percent', 'quality_return_percent',
    'avg_harga', 'total_score', 'cabang_id',
])]
class SupplierScore extends Model
{
    use LogsActivity;

    protected $table = 'supplier_scores';

    protected $casts = [
        'on_time_percent' => 'decimal:2',
        'quality_return_percent' => 'decimal:2',
        'avg_harga' => 'decimal:2',
        'total_score' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
