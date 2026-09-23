<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['produk_id', 'sku_variant_id', 'gudang_id', 'rak_id', 'jumlah', 'jumlah_minimum'])]
class StokItem extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('StokItem');
    }

    protected $casts = [
        'jumlah' => 'integer',
        'jumlah_minimum' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }
}
