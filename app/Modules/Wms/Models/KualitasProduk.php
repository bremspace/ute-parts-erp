<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'keterangan', 'is_active', 'brand_id'])]
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
        'brand_id' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'kualitas_id');
    }

    /**
     * Scope Hybrid Grade: jika brandId diberikan, ambil kualitas milik brand tsb + kualitas universal (brand_id null).
     */
    public function scopeForBrand(Builder $query, ?int $brandId = null): Builder
    {
        if ($brandId) {
            return $query->where(function ($q) use ($brandId) {
                $q->where('brand_id', $brandId)
                    ->orWhereNull('brand_id');
            });
        }

        return $query;
    }

    public function scopeUniversal(Builder $query): Builder
    {
        return $query->whereNull('brand_id');
    }

    public function scopeExclusive(Builder $query): Builder
    {
        return $query->whereNotNull('brand_id');
    }
}
