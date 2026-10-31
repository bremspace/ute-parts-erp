<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama',
    'kontak',
    'telepon',
    'email',
    'alamat',
    'termin_hari',
    'saldo_deposit',
    'bank_nama',
    'nomor_rekening',
    'nama_rekening',
    'catatan',
    'kebijakan_retur',
    'is_active',
])]
class Supplier extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'supplier';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Supplier');
    }

    protected $casts = [
        'termin_hari' => 'integer',
        'saldo_deposit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function returnPembelians(): HasMany
    {
        return $this->hasMany(ReturnPembelian::class);
    }

    public function saldoMutasi(): HasMany
    {
        return $this->hasMany(SupplierSaldoMutasi::class)->latest('id');
    }

    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class, 'supplier_id');
    }

    /**
     * Tambah saldo deposit supplier (misal dari retur pembelian).
     */
    public function tambahSaldoDeposit(
        float $jumlah,
        string $keterangan,
        $referensi = null,
        ?int $cabangId = null,
        ?int $userId = null
    ): SupplierSaldoMutasi {
        return DB::transaction(function () use ($jumlah, $keterangan, $referensi, $cabangId, $userId) {
            $fresh = self::where('id', $this->id)->lockForUpdate()->first();
            $saldoSebelum = (float) $fresh->saldo_deposit;
            $saldoSetelah = round($saldoSebelum + $jumlah, 2);

            $fresh->update(['saldo_deposit' => $saldoSetelah]);
            $this->saldo_deposit = $saldoSetelah;

            return SupplierSaldoMutasi::create([
                'supplier_id' => $fresh->id,
                'cabang_id' => $cabangId ?? session('cabang_id'),
                'referensi_tipe' => $referensi ? get_class($referensi) : null,
                'referensi_id' => $referensi?->id,
                'jenis' => 'masuk',
                'jumlah' => $jumlah,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_setelah' => $saldoSetelah,
                'keterangan' => $keterangan,
                'user_id' => $userId ?? auth()->id(),
            ]);
        });
    }

    /**
     * Kurangi saldo deposit supplier (misal untuk potong tagihan PO berikutnya).
     */
    public function kurangiSaldoDeposit(
        float $jumlah,
        string $keterangan,
        $referensi = null,
        ?int $cabangId = null,
        ?int $userId = null
    ): SupplierSaldoMutasi {
        return DB::transaction(function () use ($jumlah, $keterangan, $referensi, $cabangId, $userId) {
            $fresh = self::where('id', $this->id)->lockForUpdate()->first();
            $saldoSebelum = (float) $fresh->saldo_deposit;
            $saldoSetelah = max(0, round($saldoSebelum - $jumlah, 2));

            $fresh->update(['saldo_deposit' => $saldoSetelah]);
            $this->saldo_deposit = $saldoSetelah;

            return SupplierSaldoMutasi::create([
                'supplier_id' => $fresh->id,
                'cabang_id' => $cabangId ?? session('cabang_id'),
                'referensi_tipe' => $referensi ? get_class($referensi) : null,
                'referensi_id' => $referensi?->id,
                'jenis' => 'keluar',
                'jumlah' => $jumlah,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_setelah' => $saldoSetelah,
                'keterangan' => $keterangan,
                'user_id' => $userId ?? auth()->id(),
            ]);
        });
    }
}
