<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id',
    'cabang_id',
    'referensi_tipe',
    'referensi_id',
    'jenis',
    'jumlah',
    'saldo_sebelum',
    'saldo_setelah',
    'keterangan',
    'user_id',
])]
class SupplierSaldoMutasi extends Model
{
    protected $table = 'supplier_saldo_mutasi';

    protected $casts = [
        'jumlah' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_setelah' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
