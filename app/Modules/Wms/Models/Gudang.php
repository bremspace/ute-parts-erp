<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['cabang_id', 'nama', 'kode', 'is_active', 'is_default'])]
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
        'is_default' => 'boolean',
    ];

    public static function getDefault(?int $cabangId = null): self
    {
        $targetCabangId = $cabangId ?: (session('cabang_id') ?? auth()->user()?->cabang_id ?? Cabang::first()?->id);

        if (! $targetCabangId) {
            $cabang = Cabang::firstOrCreate(['kode' => 'CBG-01'], ['nama' => 'Cabang Utama', 'is_active' => true]);
            $targetCabangId = $cabang->id;
        }

        $default = static::where('cabang_id', $targetCabangId)->where('is_default', true)->first();
        if ($default) {
            return $default;
        }

        // Jika belum ada is_default, cari Gudang Utama / tertua lalu tandai
        $existing = static::where('cabang_id', $targetCabangId)
            ->where(function ($q) {
                $q->where('kode', 'GDG-01')->orWhere('nama', 'LIKE', '%Utama%');
            })
            ->first()
            ?? static::where('cabang_id', $targetCabangId)->where('is_active', true)->first()
            ?? static::where('cabang_id', $targetCabangId)->first();

        if ($existing) {
            $existing->update(['is_default' => true, 'is_active' => true]);

            return $existing;
        }

        // Buat baru jika belum ada gudang sama sekali untuk cabang ini
        return static::create([
            'cabang_id' => $targetCabangId,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-'.str_pad((string) $targetCabangId, 2, '0', STR_PAD_LEFT).'-01',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $model) {
            if ($model->is_default) {
                throw new \DomainException("Gudang '{$model->nama}' adalah gudang default sistem/cabang dan tidak dapat dihapus.");
            }

            $default = static::getDefault($model->cabang_id);
            if ($default->id === $model->id) {
                throw new \DomainException('Gudang default tidak dapat dihapus.');
            }

            // 1. Merger Stok: pindahkan atau gabungkan stok_items ke gudang default
            $sourceItems = DB::table('stok_items')->where('gudang_id', $model->id)->get();
            foreach ($sourceItems as $item) {
                $targetItem = DB::table('stok_items')
                    ->where('produk_id', $item->produk_id)
                    ->when($item->sku_variant_id, fn ($q) => $q->where('sku_variant_id', $item->sku_variant_id), fn ($q) => $q->whereNull('sku_variant_id'))
                    ->where('gudang_id', $default->id)
                    ->first();

                if ($targetItem) {
                    // Gabungkan kuantitas stok agar tidak ada selisih fisik
                    DB::table('stok_items')
                        ->where('id', $targetItem->id)
                        ->update([
                            'jumlah' => $targetItem->jumlah + $item->jumlah,
                            'jumlah_minimum' => max($targetItem->jumlah_minimum, $item->jumlah_minimum),
                            'updated_at' => now(),
                        ]);
                    DB::table('stok_items')->where('id', $item->id)->delete();
                } else {
                    // Pindahkan langsung baris stok ke gudang default
                    DB::table('stok_items')
                        ->where('id', $item->id)
                        ->update([
                            'gudang_id' => $default->id,
                            'updated_at' => now(),
                        ]);
                }
            }

            // 2. Alihkan seluruh rak penyimpanan ke gudang default
            DB::table('rak')->where('gudang_id', $model->id)->update([
                'gudang_id' => $default->id,
                'updated_at' => now(),
            ]);

            // 3. Alihkan referensi riwayat operasional & transaksi ke gudang default
            $tableUpdates = [
                ['table' => 'purchase_order', 'column' => 'gudang_tujuan_id'],
                ['table' => 'stok_transfer', 'column' => 'gudang_asal_id'],
                ['table' => 'stok_transfer', 'column' => 'gudang_tujuan_id'],
                ['table' => 'stok_opname', 'column' => 'gudang_id'],
                ['table' => 'stock_mutation_log', 'column' => 'gudang_id'],
                ['table' => 'stok_log', 'column' => 'gudang_id'],
                ['table' => 'grn', 'column' => 'gudang_id'],
                ['table' => 'servis_sparepart', 'column' => 'gudang_id'],
                ['table' => 'transaksi', 'column' => 'gudang_id'],
                ['table' => 'return_pembelian', 'column' => 'gudang_id'],
                ['table' => 'return_penjualan', 'column' => 'gudang_id'],
            ];

            foreach ($tableUpdates as $u) {
                if (Schema::hasTable($u['table']) && Schema::hasColumn($u['table'], $u['column'])) {
                    DB::table($u['table'])->where($u['column'], $model->id)->update([
                        $u['column'] => $default->id,
                    ]);
                }
            }
        });
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function stokItems(): HasMany
    {
        return $this->hasMany(StokItem::class);
    }

    public function raks(): HasMany
    {
        return $this->hasMany(Rak::class);
    }
}
