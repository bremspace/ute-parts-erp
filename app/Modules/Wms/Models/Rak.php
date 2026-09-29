<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['gudang_id', 'nama', 'kode', 'zona', 'is_active'])]
class Rak extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'rak';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Rak');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }
}
