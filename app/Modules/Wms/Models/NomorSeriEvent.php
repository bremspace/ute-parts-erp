<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [P1-6] Snapshot riwayat append-only per nomor seri (tabel nomor_seri_events).
 *
 * Menyimpan link LAMA (transaksi_item_id / tiket_servis_id + item) tepat sebelum
 * klaimJual/klaimServis menimpanya — sehingga rantai histori permanen tetap
 * utuh utk trace laporan/garansi. Baris tidak pernah di-update/di-hapus.
 * [F1-4] Model kritis — wajib activity log (create).
 */
#[Fillable([
    'nomor_seri_id', 'cabang_id', 'aksi', 'status_sebelum',
    'transaksi_item_id', 'tiket_servis_id', 'tiket_servis_item_id',
])]
class NomorSeriEvent extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public const AKSI_KLAIM_JUAL = 'klaim_jual';

    public const AKSI_KLAIM_SERVIS = 'klaim_servis';

    protected $table = 'nomor_seri_events';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Nomor Seri Event');
    }

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
