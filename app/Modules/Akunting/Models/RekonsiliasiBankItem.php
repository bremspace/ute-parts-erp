<?php

namespace App\Modules\Akunting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rekonsiliasi_bank_id', 'tanggal', 'deskripsi', 'tipe',
    'nominal', 'status', 'jurnal_akuntansi_id', 'no_jurnal_matched', 'catatan',
])]
class RekonsiliasiBankItem extends Model
{
    protected $table = 'rekonsiliasi_bank_item';

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];

    public function rekonsiliasi(): BelongsTo
    {
        return $this->belongsTo(RekonsiliasiBank::class, 'rekonsiliasi_bank_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(JurnalAkuntansi::class, 'jurnal_akuntansi_id');
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }
}
