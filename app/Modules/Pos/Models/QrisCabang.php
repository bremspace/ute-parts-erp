<?php

namespace App\Modules\Pos\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id',
    'payload_static',
    'merchant_name',
    'merchant_city',
    'postal_code',
    'nmid',
    'image_path',
    'is_active',
])]
class QrisCabang extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'qris_cabang';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('QrisCabang');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}
