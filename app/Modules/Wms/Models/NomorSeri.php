<?php

namespace App\Modules\Wms\Models;

use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [F2-3] Nomor seri (serial number) per produk — tabel nomor_seri_produk.
 *
 * Status: tersedia → terjual (POS) | servis (tiket servis) → kembali tersedia saat selesai.
 * Tautan riwayat (trace garansi) disimpan permanen: transaksi_item_id,
 * tiket_servis_id + tiket_servis_item_id — tidak di-clear saat status berubah.
 */
#[Fillable([
    'cabang_id', 'produk_id', 'sku_variant_id', 'nomor_seri',
    'status', 'transaksi_item_id', 'tiket_servis_id', 'tiket_servis_item_id',
    'keterangan',
])]
class NomorSeri extends Model
{
    public const STATUS_TERSEDIA = 'tersedia';

    public const STATUS_TERJUAL = 'terjual';

    public const STATUS_SERVIS = 'servis';

    public const STATUS_GARANSI = 'garansi';

    protected $table = 'nomor_seri_produk';

    protected $casts = [
        'cabang_id' => 'integer',
        'produk_id' => 'integer',
        'status' => 'string',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function transaksiItem(): BelongsTo
    {
        return $this->belongsTo(TransaksiItem::class);
    }

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function tiketServisItem(): BelongsTo
    {
        return $this->belongsTo(TiketServisItem::class);
    }

    /**
     * [P1-6] Riwayat append-only: snapshot link LAMA sebelum ditimpa klaim
     * berikutnya (2 kunjungan servis / resale) — konsumsi UI laporan next pass.
     */
    public function events(): HasMany
    {
        return $this->hasMany(NomorSeriEvent::class)->orderByDesc('id');
    }
}
