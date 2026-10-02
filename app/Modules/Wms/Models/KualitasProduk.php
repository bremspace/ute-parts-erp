<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'keterangan', 'is_active'])]
class KualitasProduk extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'kualitas_produk';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Tingkat Kualitas');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'kualitas_id');
    }
}
