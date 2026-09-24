<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-7 / G-18] Task cycle count — snapshot sample item acak (seed + item terpilih,
 * cabang-scoped) + hasil count fisik (selisih + klasifikasi minor/major).
 * Status: menunggu_count → (minor: koreksi langsung → selesai)
 *         atau (major: menunggu_approval → hook F1-1 → selesai/ditolak).
 * [F1-4] Model kritis — wajib activity log.
 */
#[Fillable([
    'cycle_count_schedule_id', 'cabang_id', 'no_task', 'tanggal',
    'tipe_target', 'target_id', 'target_kategori', 'target_label',
    'seed', 'sample_items', 'hasil', 'status',
    'threshold_unit', 'threshold_persen',
    'user_id', 'approver_id', 'counted_at', 'applied_at', 'catatan',
])]
class CycleCountTask extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'cycle_count_task';

    protected $casts = [
        'tanggal' => 'date',
        'sample_items' => 'array',
        'hasil' => 'array',
        'counted_at' => 'datetime',
        'applied_at' => 'datetime',
        'threshold_persen' => 'float',
        'threshold_unit' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Cycle Count Task');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CycleCountSchedule::class, 'cycle_count_schedule_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
