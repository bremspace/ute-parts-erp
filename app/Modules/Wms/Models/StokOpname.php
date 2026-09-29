<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_opname', 'gudang_id', 'rak_id', 'user_id', 'approver_id',
    'status', 'tanggal_approval', 'catatan_approval', 'catatan',
])]
class StokOpname extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'stok_opname';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Stok Opname');
    }

    protected $casts = [
        'tanggal_approval' => 'datetime',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StokOpnameItem::class);
    }
}
