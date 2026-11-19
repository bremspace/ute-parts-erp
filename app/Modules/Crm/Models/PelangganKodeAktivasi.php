<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode aktivasi satu-pakai untuk akun member yang BELUM memiliki kata sandi
 * (mis. member yang dibuat kasir/teknisi di toko). Kode dibangkitkan staff,
 * disampaikan ke member, lalu member menyetel kata sandinya sendiri di
 * halaman publik /aktivasi-akun — menutup celah "member tidak bisa login".
 *
 * Kode TIDAK dipakai untuk mereset kata sandi yang sudah ada; jalur itu tetap
 * lewat approval owner (entity reset_password_pelanggan).
 */
#[Fillable(['pelanggan_id', 'kode', 'kedaluwarsa_at', 'dipakai_at', 'dibuat_oleh'])]
class PelangganKodeAktivasi extends Model
{
    protected $table = 'pelanggan_kode_aktivasi';

    protected $casts = [
        'kedaluwarsa_at' => 'datetime',
        'dipakai_at' => 'datetime',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('dipakai_at')->where('kedaluwarsa_at', '>', now());
    }
}
