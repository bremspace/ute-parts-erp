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
    'alasan_estimasi', 'batas_waktu_menit', 'auto_cancel_overdue', 'pengerjaan_mulai_at',
    'pengerjaan_deadline_at', 'pengerjaan_selesai_at', 'durasi_pengerjaan_menit',
    'status_sla_kpi', 'warning_overdue_sent_at', 'token_approval', 'tanggal_terima', 'tanggal_selesai',
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
        'batas_waktu_menit' => 'integer',
        'auto_cancel_overdue' => 'boolean',
        'durasi_pengerjaan_menit' => 'integer',
        'pengerjaan_mulai_at' => 'datetime',
        'pengerjaan_deadline_at' => 'datetime',
        'pengerjaan_selesai_at' => 'datetime',
        'warning_overdue_sent_at' => 'datetime',
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

    public function progressList(): HasMany
    {
        return $this->hasMany(TiketServisProgress::class)->latest();
    }

    public function getAlasanDitolakAttribute(): ?string
    {
        return $this->statusLogs()->where('status_ke', 'ditolak')->latest()->value('alasan');
    }

    public function getTerjemahanPolaAttribute(): ?array
    {
        if ($this->tipe_kunci !== 'pola' || empty($this->kunci_terenkripsi)) {
            return null;
        }

        $raw = is_array($this->kunci_terenkripsi)
            ? $this->kunci_terenkripsi
            : explode('-', str_replace(' ', '', (string) $this->kunci_terenkripsi));

        $sequence = array_values(array_filter(array_map('intval', $raw), fn ($n) => $n >= 1 && $n <= 9));
        if (empty($sequence)) {
            return null;
        }

        $dotLabels = [
            1 => ['label' => 'Kiri Atas', 'row' => 0, 'col' => 0],
            2 => ['label' => 'Tengah Atas', 'row' => 0, 'col' => 1],
            3 => ['label' => 'Kanan Atas', 'row' => 0, 'col' => 2],
            4 => ['label' => 'Kiri Tengah', 'row' => 1, 'col' => 0],
            5 => ['label' => 'Tengah (Pusat)', 'row' => 1, 'col' => 1],
            6 => ['label' => 'Kanan Tengah', 'row' => 1, 'col' => 2],
            7 => ['label' => 'Kiri Bawah', 'row' => 2, 'col' => 0],
            8 => ['label' => 'Tengah Bawah', 'row' => 2, 'col' => 1],
            9 => ['label' => 'Kanan Bawah', 'row' => 2, 'col' => 2],
        ];

        $posisi = array_map(fn ($id) => $dotLabels[$id]['label'] ?? "Titik {$id}", $sequence);

        $arah = [];
        for ($i = 0; $i < count($sequence) - 1; $i++) {
            $from = $dotLabels[$sequence[$i]] ?? null;
            $to = $dotLabels[$sequence[$i + 1]] ?? null;
            if (! $from || ! $to) {
                continue;
            }
            $dRow = $to['row'] - $from['row'];
            $dCol = $to['col'] - $from['col'];

            $dir = match (true) {
                $dRow === 0 && $dCol > 0 => 'Kanan ➔',
                $dRow === 0 && $dCol < 0 => 'Kiri ⬅',
                $dRow > 0 && $dCol === 0 => 'Bawah ⬇',
                $dRow < 0 && $dCol === 0 => 'Atas ⬆',
                $dRow > 0 && $dCol > 0 => 'Serong Kanan-Bawah ↘',
                $dRow > 0 && $dCol < 0 => 'Serong Kiri-Bawah ↙',
                $dRow < 0 && $dCol > 0 => 'Serong Kanan-Atas ↗',
                $dRow < 0 && $dCol < 0 => 'Serong Kiri-Atas ↖',
                default => "{$sequence[$i]} ke {$sequence[$i + 1]}",
            };
            $arah[] = "{$sequence[$i]}➔{$sequence[$i + 1]} ({$dir})";
        }

        return [
            'urutan' => implode(' ➔ ', $sequence),
            'posisi' => implode(' ➔ ', $posisi),
            'arah' => ! empty($arah) ? implode(' • ', $arah) : (count($sequence) === 1 ? 'Mulai di '.$dotLabels[$sequence[0]]['label'] : '-'),
            'sequence' => $sequence,
        ];
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
