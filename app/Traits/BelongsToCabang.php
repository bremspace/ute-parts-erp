<?php

namespace App\Traits;

use App\Modules\Rbac\Models\Cabang;
use App\Scopes\CabangScope;
use App\Services\BranchContextManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToCabang
 *
 * Menerapkan Global Scope CabangScope dan otomatis mengisi
 * kolom `cabang_id` saat record dibuat jika belum diisi.
 */
trait BelongsToCabang
{
    public static function bootBelongsToCabang(): void
    {
        static::addGlobalScope(new CabangScope);

        static::creating(function ($model) {
            if (empty($model->cabang_id)) {
                $cabangId = app(BranchContextManager::class)->getActiveCabangId();
                if ($cabangId !== null) {
                    $model->cabang_id = $cabangId;
                }
            }
        });
    }

    /**
     * Relasi ke model Cabang.
     */
    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    /**
     * Query scope untuk bypass filter cabang (misal untuk konsolidasi laporan).
     */
    public function scopeWithoutCabangScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CabangScope::class);
    }
}
