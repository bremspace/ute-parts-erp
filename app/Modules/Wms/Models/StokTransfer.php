<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'no_transfer', 'gudang_asal_id', 'gudang_tujuan_id', 'user_pengirim_id',
    'user_penerima_id', 'status', 'tanggal_kirim', 'tanggal_terima', 'catatan',
])]
class StokTransfer extends Model
{
    protected $table = 'stok_transfer';

    protected $casts = [
        'tanggal_kirim' => 'datetime',
        'tanggal_terima' => 'datetime',
    ];

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_pengirim_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_penerima_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StokTransferItem::class);
    }

    /**
     * [T-13] Qty stok asal yang terkunci oleh transfer berstatus draft (pending).
     * Map "produk_id:sku_variant_id" => total qty pending — dipakai sbg guard
     * stok tersedia (StokDeductionService) & penanda (kolom virtual stok_dikunci).
     */
    public static function pendingLockedByGudang(int $gudangId, ?int $excludeTransferId = null): array
    {
        return self::query()
            ->where('gudang_asal_id', $gudangId)
            ->where('status', 'draft')
            ->when($excludeTransferId, fn ($q) => $q->where('id', '!=', $excludeTransferId))
            ->with('items')
            ->get()
            ->flatMap(fn ($t) => $t->items)
            ->reduce(function (array $carry, StokTransferItem $item): array {
                $key = $item->produk_id.':'.($item->sku_variant_id ?? 'null');
                $carry[$key] = ($carry[$key] ?? 0) + $item->jumlah;

                return $carry;
            }, []);
    }

    /**
     * [T-13] Qty terkunci utk satu kombinasi produk/varian di gudang asal.
     */
    public static function pendingLockedFor(StokItem $stok, ?int $excludeTransferId = null): int
    {
        return (int) (self::pendingLockedByGudang($stok->gudang_id, $excludeTransferId)[
            $stok->produk_id.':'.($stok->sku_variant_id ?? 'null')
        ] ?? 0);
    }
}
