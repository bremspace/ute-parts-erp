<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [P1-6] Snapshot riwayat append-only per nomor seri (tabel nomor_seri_events).
 *
 * Menyimpan link LAMA (transaksi_item_id / tiket_servis_id + item) tepat sebelum
 * klaimJual/klaimServis menimpanya — sehingga rantai histori permanen tetap
 * utuh utk trace laporan/garansi. Baris tidak pernah di-update/di-hapus.
 */
#[Fillable([
    'nomor_seri_id', 'cabang_id', 'aksi', 'status_sebelum',
    'transaksi_item_id', 'tiket_servis_id', 'tiket_servis_item_id',
])]
class NomorSeriEvent extends Model
{
    public const AKSI_KLAIM_JUAL = 'klaim_jual';

    public const AKSI_KLAIM_SERVIS = 'klaim_servis';

    protected $table = 'nomor_seri_events';

    protected $casts = [
        'nomor_seri_id' => 'integer',
        'cabang_id' => 'integer',
        'transaksi_item_id' => 'integer',
        'tiket_servis_id' => 'integer',
        'tiket_servis_item_id' => 'integer',
    ];

    public function nomorSeri(): BelongsTo
    {
        return $this->belongsTo(NomorSeri::class);
    }
}
