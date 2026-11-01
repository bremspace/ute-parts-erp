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
    'idempotency_key', 'user_id', 'is_reversed', 'reversed_by_no_jurnal',
    'reversal_of_no_jurnal', 'reversed_at', 'reversed_by_user_id',
    'status_approval', 'approved_by_user_id', 'approved_at', 'catatan_approval',
    'lampiran_path', 'lampiran_nama',
])]
class JurnalHeader extends Model
{
    protected $table = 'jurnal_header';

    protected $casts = [
        'tanggal' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'jumlah_baris' => 'integer',
        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JurnalAkuntansi::class, 'no_jurnal', 'no_jurnal');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /**
     * [ISO 9001 / ISO 27001] Integritas Jurnal Akuntansi:
     * - Header jurnal tidak dapat dihapus (deleting blocked).
     * - Atribut finansial inti (no_jurnal, nominal, tanggal, cabang) tidak dapat diubah (updating dirty check).
     * - Hanya metadata lifecycle (status reversal, approval maker-checker, lampiran berkas) yang diizinkan diperbarui.
     */
    protected static function booted(): void
    {
        static::updating(function (self $header) {
            $immutableFields = [
                'no_jurnal', 'cabang_id', 'cabang_key', 'tanggal', 'sumber',
                'total_debit', 'total_kredit', 'jumlah_baris', 'idempotency_key',
            ];

            if ($header->isDirty($immutableFields)) {
                throw new \DomainException('Atribut inti jurnal akuntansi bersifat immutable demi kepatuhan ISO/audit.');
            }
        });

        static::deleting(function () {
            throw new \DomainException('Header jurnal akuntansi bersifat immutable (tidak dapat dihapus) demi kepatuhan ISO/audit.');
        });
    }

    /**
     * Apakah jurnal sudah balance (header vs jumlah baris).
     */
    public function balanced(): bool
    {
        return round((float) $this->total_debit, 2) === round((float) $this->total_kredit, 2);
    }
}
