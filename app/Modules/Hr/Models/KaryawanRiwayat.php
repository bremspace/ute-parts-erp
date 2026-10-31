<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [F3-8 / History] Riwayat Karir, Mutasi, Promosi, Gaji, dan Status Karyawan.
 */
#[Fillable([
    'karyawan_id',
    'user_id',
    'tipe_event',
    'tanggal_efektif',
    'judul',
    'deskripsi',
    'jabatan_lama',
    'jabatan_baru',
    'cabang_lama_id',
    'cabang_baru_id',
    'gaji_lama',
    'gaji_baru',
    'dokumen_referensi',
])]
class KaryawanRiwayat extends Model
{
    protected $table = 'karyawan_riwayat';

    protected $casts = [
        'tanggal_efektif' => 'date',
        'gaji_lama' => 'decimal:2',
        'gaji_baru' => 'decimal:2',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cabangLama(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_lama_id');
    }

    public function cabangBaru(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_baru_id');
    }
}
