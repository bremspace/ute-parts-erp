<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'gudang_id', 'produk_id', 'sku_variant_id', 'user_id',
    'jenis', 'referensi_tipe', 'referensi_id',
    'jumlah_sebelum', 'perubahan', 'jumlah_setelah', 'catatan',
])]
class StokLog extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'stok_log';

    /**
     * [B-10d / P1-3] Audit trail mutasi stok.
     *
     * `logOnly` — HANYA kolom sensitif (jenis mutasi + saldo sebelum/perubahan/
     * sesudah + asal mutasi). `catatan` free-text tidak dilog agar isi log tetap
     * ramping. `cabang_id` tidak ada di tabel; `CatatAktivitas` menurunkannya
     * dari `gudang_id` (AktivitasCabang) sehingga log tetap bisa disaring per
     * cabang tanpa bocor lintas cabang.
     *
     * `logOnlyDirty` + `dontLogEmptyChanges()` — baris stok log Immutable di
     * aplikasi (insert-only), jadi event `updated` nyaris tak pernah terjadi
     * dan tidak ada log kosong yang memboroskan storage.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'gudang_id', 'produk_id', 'sku_variant_id', 'jenis',
                'referensi_tipe', 'referensi_id',
                'jumlah_sebelum', 'perubahan', 'jumlah_setelah',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Log Stok '.self::labelAksiAktivitas($event));
    }

    protected $casts = [
        'jumlah_sebelum' => 'integer',
        'perubahan' => 'integer',
        'jumlah_setelah' => 'integer',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
