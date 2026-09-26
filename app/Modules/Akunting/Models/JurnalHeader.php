<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [B-10a / P0-3] Header 1 entri jurnal double-entry.
 *
 * `jurnal_akuntansi` tetap line-based; tabel ini menjadi "kunci" idempotensi:
 * unique (cabang_key, no_jurnal) membuat posting ganda mustahil di level DB.
 */
#[Fillable([
    'no_jurnal', 'cabang_id', 'cabang_key', 'tanggal', 'sumber', 'deskripsi',
    'total_debit', 'total_kredit', 'jumlah_baris', 'referensi_tipe', 'referensi_id',
    'idempotency_key', 'user_id',
])]
class JurnalHeader extends Model
{
    protected $table = 'jurnal_header';

    protected $casts = [
        'tanggal' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'jumlah_baris' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JurnalAkuntansi::class, 'no_jurnal', 'no_jurnal');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Apakah jurnal sudah balance (header vs jumlah baris).
     */
    public function balanced(): bool
    {
        return round((float) $this->total_debit, 2) === round((float) $this->total_kredit, 2);
    }
}
