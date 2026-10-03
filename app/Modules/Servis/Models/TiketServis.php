<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_tiket', 'cabang_id', 'transaksi_id', 'jenis_servis_id', 'pelanggan_id', 'teknisi_id',
    'nama_pelanggan', 'telepon_pelanggan', 'jenis_hp', 'seri_hp', 'tipe_kunci', 'kunci_terenkripsi',
    'keluhan', 'kondisi_fisik', 'foto_unit', 'status', 'sumber', 'estimasi_biaya',
    'alasan_estimasi', 'token_approval', 'tanggal_terima', 'tanggal_selesai',
    'tanggal_diambil', 'catatan_admin',
    'status_pembayaran', 'tanggal_bayar', 'metode_pembayaran', 'no_jurnal_bayar',
])]
class TiketServis extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'tiket_servis';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Tiket Servis');
    }

    protected $casts = [
        'kondisi_fisik' => 'array',
        'foto_unit' => 'array',
        'estimasi_biaya' => 'decimal:2',
        'tanggal_terima' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'tanggal_diambil' => 'datetime',
        'tanggal_bayar' => 'datetime',
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

    public function estimasiItems(): HasMany
    {
        return $this->hasMany(TiketServisEstimasiItem::class);
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /**
     * Rincian lengkap item servis (Jasa & Part) untuk billing, struk thermal, dan riwayat.
     *
     * @return array{
     *     items: array<int, array{tipe: string, nama: string, qty: int, harga: float, subtotal: float}>,
     *     total_jasa: float,
     *     total_part: float,
     *     total: float
     * }
     */
    public function getRincianBiayaLengkap(): array
    {
        $pekerjaanItems = $this->items()->whereNull('dibatalkan_at')->with(['produk', 'skuVariant'])->get();
        $sparepartsLegacy = $this->spareparts()->whereNull('dibatalkan_at')->with(['produk', 'skuVariant'])->get();
        $estimasiItems = $this->estimasiItems()->with('produk')->get();

        $rincian = [];
        $totalJasa = 0.0;
        $totalPart = 0.0;

        $hasPekerjaan = $pekerjaanItems->isNotEmpty() || $sparepartsLegacy->isNotEmpty();

        if ($hasPekerjaan) {
            // 1. Jasa dari pekerjaan teknisi
            $jasaPekerjaan = $pekerjaanItems->where('tipe', 'jasa');
            if ($jasaPekerjaan->isNotEmpty()) {
                foreach ($jasaPekerjaan as $j) {
                    $sub = (float) ($j->harga * $j->qty);
                    $totalJasa += $sub;
                    $rincian[] = [
                        'tipe' => 'jasa',
                        'nama' => '[Jasa] '.$j->nama_item,
                        'qty' => (int) $j->qty,
                        'harga' => (float) $j->harga,
                        'subtotal' => $sub,
                    ];
                }
            } elseif ($estimasiItems->where('tipe', 'jasa')->isNotEmpty()) {
                foreach ($estimasiItems->where('tipe', 'jasa') as $ej) {
                    $sub = (float) ($ej->harga * $ej->qty);
                    $totalJasa += $sub;
                    $rincian[] = [
                        'tipe' => 'jasa',
                        'nama' => '[Jasa] '.$ej->nama_item,
                        'qty' => (int) $ej->qty,
                        'harga' => (float) $ej->harga,
                        'subtotal' => $sub,
                    ];
                }
            } else {
                $estimasi = (float) ($this->estimasi_biaya ?? 0);
                if ($estimasi > 0 && $pekerjaanItems->where('tipe', 'part')->isEmpty()) {
                    $totalJasa += $estimasi;
                    $rincian[] = [
                        'tipe' => 'jasa',
                        'nama' => '[Jasa] Perbaikan '.$this->jenis_hp,
                        'qty' => 1,
                        'harga' => $estimasi,
                        'subtotal' => $estimasi,
                    ];
                }
            }

            // 2. Part dari pekerjaan teknisi
            foreach ($pekerjaanItems->where('tipe', 'part') as $p) {
                $sub = (float) ($p->harga * $p->qty);
                $totalPart += $sub;
                $nama = $p->produk?->nama ?: $p->nama_item;
                if ($p->skuVariant?->nama_varian) {
                    $nama .= ' ('.$p->skuVariant->nama_varian.')';
                }
                $rincian[] = [
                    'tipe' => 'part',
                    'nama' => '[Part] '.$nama,
                    'qty' => (int) $p->qty,
                    'harga' => (float) $p->harga,
                    'subtotal' => $sub,
                ];
            }

            // 3. Part legacy (ServisSparepart)
            foreach ($sparepartsLegacy as $sp) {
                $sub = (float) ($sp->harga_satuan * $sp->jumlah);
                $totalPart += $sub;
                $nama = $sp->produk?->nama ?: 'Sparepart';
                if ($sp->skuVariant?->nama_varian) {
                    $nama .= ' ('.$sp->skuVariant->nama_varian.')';
                }
                $rincian[] = [
                    'tipe' => 'part',
                    'nama' => '[Part] '.$nama,
                    'qty' => (int) $sp->jumlah,
                    'harga' => (float) $sp->harga_satuan,
                    'subtotal' => $sub,
                ];
            }

            // Bila di pekerjaan tidak ada part tapi di estimasi ada part
            if ($pekerjaanItems->where('tipe', 'part')->isEmpty() && $sparepartsLegacy->isEmpty() && $estimasiItems->where('tipe', 'part')->isNotEmpty()) {
                foreach ($estimasiItems->where('tipe', 'part') as $ep) {
                    $sub = (float) ($ep->harga * $ep->qty);
                    $totalPart += $sub;
                    $nama = $ep->produk?->nama ?: $ep->nama_item;
                    $rincian[] = [
                        'tipe' => 'part',
                        'nama' => '[Part] '.$nama,
                        'qty' => (int) $ep->qty,
                        'harga' => (float) $ep->harga,
                        'subtotal' => $sub,
                    ];
                }
            }
        } elseif ($estimasiItems->isNotEmpty()) {
            foreach ($estimasiItems as $ei) {
                $sub = (float) ($ei->harga * $ei->qty);
                if ($ei->tipe === 'part') {
                    $totalPart += $sub;
                    $nama = $ei->produk?->nama ?: $ei->nama_item;
                    $rincian[] = [
                        'tipe' => 'part',
                        'nama' => '[Part] '.$nama,
                        'qty' => (int) $ei->qty,
                        'harga' => (float) $ei->harga,
                        'subtotal' => $sub,
                    ];
                } else {
                    $totalJasa += $sub;
                    $rincian[] = [
                        'tipe' => 'jasa',
                        'nama' => '[Jasa] '.($ei->nama_item ?: 'Jasa Servis'),
                        'qty' => (int) $ei->qty,
                        'harga' => (float) $ei->harga,
                        'subtotal' => $sub,
                    ];
                }
            }
        } else {
            $biaya = (float) ($this->estimasi_biaya ?? 0);
            $totalJasa = $biaya;
            if ($biaya > 0) {
                $rincian[] = [
                    'tipe' => 'jasa',
                    'nama' => '[Jasa] Perbaikan '.$this->jenis_hp,
                    'qty' => 1,
                    'harga' => $biaya,
                    'subtotal' => $biaya,
                ];
            }
        }

        return [
            'items' => $rincian,
            'total_jasa' => $totalJasa,
            'total_part' => $totalPart,
            'total' => $totalJasa + $totalPart,
        ];
    }
}
