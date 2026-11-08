<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'produk_id', 'sku_variant_id', 'gudang_id', 'user_id', 'delta', 'sumber',
    'referensi_tipe', 'referensi_id', 'terjadi_at',
])]
class StockMutationLog extends Model
{
    protected $table = 'stock_mutation_log';

    protected $casts = [
        'delta' => 'integer',
        'terjadi_at' => 'datetime',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    /** [B-10b/P1-2]_nullable — writer yang belum menyetelkannya (mis. StokDeductionService) tetap NULL. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'referensi_tipe', 'referensi_id');
    }

    /**
     * [ISO 9001 / ISO 27001 / ISO 25010] Integritas Ledger Mutasi Stok:
     * Baris log mutasi stok bersifat append-only / immutable (tidak dapat diubah atau dihapus).
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Baris log mutasi stok bersifat immutable (tidak dapat diubah) demi kepatuhan ISO/audit.');
        });

        static::deleting(function () {
            throw new \DomainException('Baris log mutasi stok bersifat immutable (tidak dapat dihapus) demi kepatuhan ISO/audit.');
        });
    }
}
