<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['merk', 'model', 'nama', 'is_active'])]
class TipeHp extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'tipe_hp';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Tipe HP');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(
            Produk::class,
            'kompatibilitas_produk_tipe_hp',
            'tipe_hp_id',
            'produk_id'
        );
    }
}
