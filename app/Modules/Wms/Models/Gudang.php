<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['cabang_id', 'nama', 'kode', 'is_active'])]
class Gudang extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'gudang';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Gudang');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function stokItems(): HasMany
    {
        return $this->hasMany(StokItem::class);
    }
}
