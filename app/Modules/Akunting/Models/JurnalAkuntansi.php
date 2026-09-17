<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'no_jurnal', 'tanggal', 'cabang_id', 'akun_coa_id', 'sumber',
    'deskripsi', 'debit', 'kredit', 'referensi_tipe', 'referensi_id', 'user_id'
])]
class JurnalAkuntansi extends Model
{
    protected $table = 'jurnal_akuntansi';

    protected $casts = [
        'tanggal' => 'datetime',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunCOA::class, 'akun_coa_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}