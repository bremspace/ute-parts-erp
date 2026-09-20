<?php

namespace App\Modules\Servis\Models;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'no_tiket', 'cabang_id', 'jenis_servis_id', 'pelanggan_id', 'teknisi_id',
    'nama_pelanggan', 'telepon_pelanggan', 'jenis_hp', 'seri_hp', 'tipe_kunci', 'kunci_terenkripsi',
    'keluhan', 'kondisi_fisik', 'foto_unit', 'status', 'sumber', 'estimasi_biaya',
    'alasan_estimasi', 'token_approval', 'tanggal_terima', 'tanggal_selesai',
    'tanggal_diambil', 'catatan_admin'
])]
class TiketServis extends Model
{
    protected $table = 'tiket_servis';

    protected $casts = [
        'kondisi_fisik' => 'array',
        'foto_unit' => 'array',
        'estimasi_biaya' => 'decimal:2',
        'tanggal_terima' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'tanggal_diambil' => 'datetime',
        'kunci_terenkripsi' => 'encrypted', // [T-19] terenkripsi at-rest
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function jenisServis(): BelongsTo
    {
        return $this->belongsTo(JenisServis::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ServisStatusLog::class)->latest();
    }

    public function garansi(): HasOne
    {
        return $this->hasOne(Garansi::class);
    }

    public function spareparts(): HasMany
    {
        return $this->hasMany(ServisSparepart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiketServisItem::class); // [T-17] split part & jasa
    }
}