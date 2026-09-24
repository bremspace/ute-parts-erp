<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-3] Aset Tetap — register penyusutan garis lurus (PRD-Advanced §4 F3-3).
 *
 * - Penyusutan bulanan = harga_perolehan / umur_bulan (aksesor depresiasi_bulanan).
 * - Sisa buku = harga_perolehan − akumulasi_depresiasi (aksesor sisa_buku).
 * - Status: aktif → masih diproses job bulanan; fully_dep → habis umur ekonomis,
 *   berhenti posting; disposal → sudah dilepas (write-off jurnal).
 * - Idempoten per periode: kolom depresiasi_terakhir_bulan (YYYY-MM) +
 *   cek jurnal existing no_jurnal deterministik (DepresiasiService).
 * - WAJIB scoping cabang_id (kolom NOT NULL — semua query rentan lintas cabang).
 */
#[Fillable([
    'cabang_id', 'nama', 'kategori', 'harga_perolehan', 'tanggal_perolehan',
    'umur_bulan', 'metode', 'akumulasi_depresiasi', 'depresiasi_terakhir_bulan',
    'status', 'catatan', 'user_id',
])]
class AsetTetap extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'aset_tetap';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Aset Tetap');
    }

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'harga_perolehan' => 'decimal:2',
        'akumulasi_depresiasi' => 'decimal:2',
        'umur_bulan' => 'integer',
        'status' => 'string',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Scope wajib — semua bacaan aset HARUS ke cabang aktif. */
    public function scopeForCabang(Builder $query, ?int $cabangId): Builder
    {
        return $query->where('cabang_id', $cabangId);
    }

    /** Penyusutan bulanan (garis lurus) = harga_perolehan / umur_bulan. */
    public function getDepresiasiBulananAttribute(): float
    {
        $umur = (int) $this->umur_bulan;

        return $umur > 0 ? round((float) $this->harga_perolehan / $umur, 2) : 0.0;
    }

    /** Sisa buku = harga_perolehan − akumulasi_depresiasi. */
    public function getSisaBukuAttribute(): float
    {
        return round((float) $this->harga_perolehan - (float) $this->akumulasi_depresiasi, 2);
    }
}
