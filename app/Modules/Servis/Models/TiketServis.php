<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Models\WebReview;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Wms\Models\Produk;
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
    'alasan_estimasi', 'batas_waktu_menit', 'durasi_garansi_custom', 'auto_cancel_overdue', 'pengerjaan_mulai_at',
    'pengerjaan_deadline_at', 'pengerjaan_selesai_at', 'durasi_pengerjaan_menit',
    'status_sla_kpi', 'warning_overdue_sent_at', 'token_approval', 'tanggal_terima', 'tanggal_selesai',
    'tanggal_diambil', 'catatan_admin',
    'status_pembayaran', 'tanggal_bayar', 'metode_pembayaran', 'no_jurnal_bayar',
    'offline_pickup_uuid', 'is_offline_pickup', 'pickup_synced_at',
    'revisi_lingkup_hash', 'revisi_lingkup_diajukan_hash', 'revisi_lingkup_disetujui_at',
    'revisi_lingkup_diajukan_at', 'revisi_lingkup_snapshot', 'revisi_lingkup_pending',
    'stok_estimasi_diproses', 'stok_estimasi_catatan',
    'tiket_servis_lama_id', 'garansi_berlaku', 'komplain_catatan',
    'komplain_gratis', 'komplain_gratis_proposed_by', 'komplain_gratis_proposed_at',
    'komplain_gratis_approved_by', 'komplain_gratis_approved_at',
]) ]
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
        'durasi_garansi_custom' => 'integer',
        'auto_cancel_overdue' => 'boolean',
        'stok_estimasi_diproses' => 'boolean',
        'revisi_lingkup_disetujui_at' => 'datetime',
        'revisi_lingkup_diajukan_at' => 'datetime',
        'revisi_lingkup_pending' => 'boolean',
        'garansi_berlaku' => 'boolean',
        'komplain_gratis' => 'boolean',
        'komplain_gratis_proposed_at' => 'datetime',
        'komplain_gratis_approved_at' => 'datetime',
        'durasi_pengerjaan_menit' => 'integer',
        'pengerjaan_mulai_at' => 'datetime',
        'pengerjaan_deadline_at' => 'datetime',
        'pengerjaan_selesai_at' => 'datetime',
        'warning_overdue_sent_at' => 'datetime',
        'tanggal_terima' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'tanggal_diambil' => 'datetime',
        'tanggal_bayar' => 'datetime',
        'is_offline_pickup' => 'boolean',
        'pickup_synced_at' => 'datetime',
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

    public function tiketLama(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class, 'tiket_servis_lama_id');
    }

    public function spareparts(): HasMany
    {
        return $this->hasMany(ServisSparepart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiketServisItem::class); // [T-17] split part & jasa
    }

    /**
     * [P2-2/T-05] Apakah tiket pernah dibuatkan baris jasa eksplisit
     * (`TiketServisItem` tipe jasa, termasuk yang sudah dibatalkan)?
     *
     * Fallback `estimasi_biaya` hanya sah untuk tiket LEGACY yang tidak pernah
     * punya item jasa. Kalau item jasa pernah ada lalu SEMUA dibatalkan, jasa
     * = 0 — menagih `estimasi_biaya` adalah overbill (pekerjaan tidak jadi).
     */
    public function punyaRiwayatJasaEksplisit(): bool
    {
        return $this->items()->where('tipe', 'jasa')->exists();
    }

    /**
     * [P2-2/T-05-AUDIT] Apakah tiket pernah dibuatkan baris part eksplisit
     * (pekerjaan teknisi atau legacy spareparts, termasuk yang dibatalkan)?
     */
    public function punyaRiwayatPartEksplisit(): bool
    {
        return $this->items()->where('tipe', 'part')->exists()
            || $this->spareparts()->exists();
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
     * Item penagihan efektif — SATU sumber kebenaran untuk rincian tagihan,
     * jurnal `onSelesai`, dan pelunasan `bayar()`.
     *
     * Aturan gabung (estimasi vs pekerjaan):
     *  - Jasa: bila pernah ada item jasa pekerjaan (aktif atau dibatalkan),
     *    jasa = item pekerjaan AKTIF (estimasi TIDAK dipakai — hindari overbill
     *    saat jasa dibatalkan). Bila belum pernah ada item jasa, pakai jasa
     *    estimasi yang disetujui pelanggan.
     *  - Part: semua item pekerjaan aktif + sparepart legacy; item estimasi
     *    dipakai hanya bila produk/variannya BELUM PERNAH dicatat sebagai
     *    pekerjaan (aktif maupun dibatalkan). Estimasi yang sudah disetujui
     *    pelanggan tetap ditagih, tanpa dobel dengan catatan teknisi.
     *  - Tiket legacy tanpa item: fallback `estimasi_biaya` sebagai jasa.
     *
     * @return array<int, array{tipe: string, nama: string, qty: int, harga: float, subtotal: float, hpp: float, sumber: string, produk_id: ?int, sku_variant_id: ?int}>
     */
    public function getItemsPenagihan(): array
    {
        $pekerjaanAktif = $this->items()->whereNull('dibatalkan_at')->get();
        $riwayatPart = $this->items()->where('tipe', 'part')->get();
        $legacy = $this->spareparts()->whereNull('dibatalkan_at')->get();
        $estimasi = $this->estimasiItems()->get();

        // Kunci part yang sudah pernah dicatat (pekerjaan AKTIF maupun
        // dibatalkan) atau lewat sparepart legacy — dipakai untuk mencegah
        // penagihan ganda estimasi part yang sudah digantikan catatan teknisi.
        $kunciPartRiwayat = collect(array_unique(array_merge(
            $riwayatPart->map(fn ($p) => $this->kunciItemPart($p->produk_id, $p->sku_variant_id))->all(),
            $legacy->map(fn ($s) => $this->kunciItemPart($s->produk_id, $s->sku_variant_id))->all(),
        )))->flip();

        $items = [];

        // --- Jasa ---
        if ($this->punyaRiwayatJasaEksplisit()) {
            foreach ($pekerjaanAktif->where('tipe', 'jasa') as $j) {
                $items[] = $this->susunItemPenagihan('jasa', $j->nama_item, (int) $j->qty, (float) $j->harga, (float) $j->hpp, 'pekerjaan', $j->produk_id, $j->sku_variant_id);
            }
        } else {
            foreach ($estimasi->where('tipe', 'jasa') as $e) {
                $items[] = $this->susunItemPenagihan('jasa', $e->nama_item, (int) $e->qty, (float) $e->harga, 0.0, 'estimasi', $e->produk_id, $e->sku_variant_id);
            }
        }

        // --- Part: pekerjaan + legacy + estimasi yang belum tercakup ---
        foreach ($pekerjaanAktif->where('tipe', 'part') as $p) {
            $items[] = $this->susunItemPenagihan('part', $p->nama_item ?: ($p->produk?->nama ?? 'Sparepart'), (int) $p->qty, (float) $p->harga, (float) $p->hpp, 'pekerjaan', $p->produk_id, $p->sku_variant_id);
        }

        foreach ($legacy as $sp) {
            $items[] = $this->susunItemPenagihan('part', $sp->produk?->nama ?? 'Sparepart', (int) $sp->jumlah, (float) $sp->harga_satuan, (float) $sp->hpp, 'legacy', $sp->produk_id, $sp->sku_variant_id);
        }

        foreach ($estimasi->where('tipe', 'part') as $e) {
            if ($e->produk_id && $kunciPartRiwayat->has($this->kunciItemPart((int) $e->produk_id, $e->sku_variant_id))) {
                continue; // sudah dicatat teknisi (atau legacy) — catatan itu yang menang
            }

            $hpp = $e->produk_id ? (float) (Produk::find($e->produk_id)?->harga_beli ?? 0) : 0.0;
            $items[] = $this->susunItemPenagihan('part', $e->nama_item ?: ($e->produk?->nama ?? 'Sparepart'), (int) $e->qty, (float) $e->harga, $hpp, 'estimasi', $e->produk_id, $e->sku_variant_id);
        }

        // --- Fallback tiket legacy (tanpa item sama sekali) ---
        if ($items === [] && ! $this->punyaRiwayatJasaEksplisit() && ! $this->punyaRiwayatPartEksplisit()) {
            $biaya = (float) ($this->estimasi_biaya ?? 0);
            if ($biaya > 0) {
                $items[] = $this->susunItemPenagihan('jasa', 'Perbaikan '.$this->jenis_hp, 1, $biaya, 0.0, 'legacy', null, null);
            }
        }

        return $items;
    }

    /**
     * Total HPP item penagihan (pekerjaan memakai hpp tercatat; estimasi part
     * memakai harga beli produk). Dipakai jurnal HPP pada `onSelesai`.
     */
    public function getTotalHppPenagihan(): float
    {
        return (float) collect($this->getItemsPenagihan())
            ->sum(fn ($i) => (float) $i['hpp'] * (int) $i['qty']);
    }

    /**
     * Diff lingkup: item pekerjaan teknisi vs estimasi yang disetujui pelanggan.
     * Mencakup penambahan, perubahan (qty/harga — termasuk harga jasa manual),
     * dan pengurangan jasa. Bagian yang berkurang untuk part ditangani jalur
     * re-estimasi (`setEstimasi` → menunggu_approval).
     *
     * @return array{tambah: array, ubah: array, berkurang: array}
     */
    public function getPerubahanLingkup(): array
    {
        $estimasi = $this->estimasiItems()->get();

        // Tanpa estimasi item (tiket legacy) tidak ada baseline → tidak ada
        // perubahan lingkup yang bisa dibandingkan.
        if ($estimasi->isEmpty()) {
            return ['tambah' => [], 'ubah' => [], 'berkurang' => []];
        }

        $penagihan = collect($this->getItemsPenagihan());

        $estimasiJasa = $estimasi->where('tipe', 'jasa');
        $penagihanJasa = $penagihan->where('tipe', 'jasa');
        $estimasiPart = $estimasi->where('tipe', 'part');
        $penagihanPart = $penagihan->where('tipe', 'part');

        $tambah = [];
        $ubah = [];
        $berkurang = [];

        // --- Jasa: bandingkan total nilai penagihan aktual vs estimasi ---
        $totalEstimasiJasa = (float) $estimasiJasa->sum(fn ($e) => (float) $e->harga * (int) $e->qty);
        $totalPenagihanJasa = (float) $penagihanJasa->sum(fn ($p) => (float) $p['subtotal']);

        if ($estimasiJasa->isEmpty()) {
            foreach ($penagihanJasa as $p) {
                $tambah[] = $this->deskripsiPerubahan('jasa', $p['nama'], (int) $p['qty'], (float) $p['harga']);
            }
        } elseif ($totalPenagihanJasa <= 0.01) {
            // Estimasi punya jasa tapi penagihan jasa nol → berkurang
            if ($totalEstimasiJasa > 0.01) {
                $berkurang[] = [
                    'tipe' => 'jasa',
                    'nama' => 'Jasa servis',
                    'estimasi' => round($totalEstimasiJasa, 2),
                    'aktual' => 0.0,
                ];
            }
        } elseif (abs($totalPenagihanJasa - $totalEstimasiJasa) > 0.01) {
            $ubah[] = [
                'tipe' => 'jasa',
                'nama' => 'Jasa servis',
                'estimasi' => round($totalEstimasiJasa, 2),
                'aktual' => round($totalPenagihanJasa, 2),
            ];
        }

        // --- Part: per kunci produk/varian ---
        $estimasiPartKunci = $estimasiPart->mapWithKeys(
            fn ($e) => [$this->kunciItemPart((int) $e->produk_id, $e->sku_variant_id) => $e]
        );

        foreach ($penagihanPart as $p) {
            // Item yang berasal dari estimasi itu sendiri tidak mungkin
            // "di luar estimasi" — yang dibandingkan adalah catatan teknisi.
            if (($p['sumber'] ?? '') === 'estimasi') {
                continue;
            }

            $kunci = $p['produk_id'] ? $this->kunciItemPart((int) $p['produk_id'], $p['sku_variant_id']) : null;
            $padanan = $kunci ? ($estimasiPartKunci[$kunci] ?? null) : null;

            if (! $padanan) {
                $tambah[] = $this->deskripsiPerubahan('part', $p['nama'], (int) $p['qty'], (float) $p['harga']);

                continue;
            }

            if (abs((float) $p['harga'] - (float) $padanan->harga) > 0.01 || (int) $p['qty'] !== (int) $padanan->qty) {
                $ubah[] = [
                    'tipe' => 'part',
                    'nama' => $p['nama'],
                    'estimasi' => round((float) $padanan->harga * (int) $padanan->qty, 2),
                    'aktual' => round((float) $p['subtotal'], 2),
                ];
            }
        }

        return ['tambah' => $tambah, 'ubah' => $ubah, 'berkurang' => $berkurang];
    }

    /**
     * Daftar datar item di luar estimasi (untuk UI & rincian).
     *
     * @return array<int, array{nama: string, subtotal: float, jenis: string}>
     */
    public function getItemDiluarEstimasi(): array
    {
        $detail = $this->getPerubahanLingkup();
        $hasil = [];

        foreach ($detail['tambah'] as $item) {
            $hasil[] = [
                'nama' => $item['nama'],
                'subtotal' => (float) $item['subtotal'],
                'jenis' => 'tambah',
            ];
        }

        foreach ($detail['ubah'] as $item) {
            $hasil[] = [
                'nama' => $item['nama'],
                'subtotal' => (float) $item['aktual'],
                'jenis' => 'ubah',
            ];
        }

        foreach ($detail['berkurang'] as $item) {
            $hasil[] = [
                'nama' => $item['nama'],
                'subtotal' => 0.0,
                'jenis' => 'berkurang',
            ];
        }

        return $hasil;
    }

    public function punyaPerubahanLingkup(): bool
    {
        return $this->getItemDiluarEstimasi() !== [];
    }

    /**
     * Hash isi perubahan — mengikat persetujuan pada isi yang spesifik.
     */
    public function hashPerubahanLingkup(): ?string
    {
        if (! $this->punyaPerubahanLingkup()) {
            return null;
        }

        return hash('sha256', (string) json_encode($this->getPerubahanLingkup()));
    }

    /**
     * Perlu persetujuan ulang pelanggan? (perubahan ada & belum ada
     * persetujuan yang cocok dengan isi perubahan saat ini).
     */
    public function perluPersetujuanUlang(): bool
    {
        $hash = $this->hashPerubahanLingkup();

        return $hash !== null && $hash !== $this->revisi_lingkup_hash;
    }

    /**
     * Item part estimasi yang dipakai & belum dipotong stoknya.
     *
     * @return array<int, array{nama: string, produk_id: int, sku_variant_id: ?int, qty: int}>
     */
    public function getItemEstimasiPerluStok(): array
    {
        if ($this->stok_estimasi_diproses) {
            return [];
        }

        return collect($this->getItemsPenagihan())
            ->where('tipe', 'part')
            ->where('sumber', 'estimasi')
            ->filter(fn ($i) => ! empty($i['produk_id']))
            ->map(fn ($i) => [
                'nama' => $i['nama'],
                'produk_id' => (int) $i['produk_id'],
                'sku_variant_id' => $i['sku_variant_id'],
                'qty' => (int) $i['qty'],
            ])
            ->values()
            ->all();
    }

    protected function deskripsiPerubahan(string $tipe, string $nama, int $qty, float $harga): array
    {
        return [
            'tipe' => $tipe,
            'nama' => $nama,
            'qty' => $qty,
            'harga' => $harga,
            'subtotal' => round($harga * $qty, 2),
        ];
    }

    protected function susunItemPenagihan(string $tipe, string $nama, int $qty, float $harga, float $hpp, string $sumber, ?int $produkId, ?int $skuVariantId): array
    {
        $qty = max(1, $qty);

        return [
            'tipe' => $tipe,
            'nama' => $nama,
            'qty' => $qty,
            'harga' => $harga,
            'subtotal' => round($harga * $qty, 2),
            'hpp' => $hpp,
            'sumber' => $sumber,
            'produk_id' => $produkId,
            'sku_variant_id' => $skuVariantId,
        ];
    }

    protected function kunciItemPart(?int $produkId, ?int $variantId): string
    {
        return ($produkId ?? 0).'|'.($variantId ?? 'null');
    }

    /**
     * Rincian lengkap item servis (Jasa & Part) untuk billing, struk thermal, dan riwayat.
     *
     * @return array{
     *     items: array<int, array{tipe: string, nama: string, qty: int, harga: float, subtotal: float, sumber: string}>,
     *     total_jasa: float,
     *     total_part: float,
     *     total: float
     * }
     */
    public function getRincianBiayaLengkap(): array
    {
        $rincian = [];
        $totalJasa = 0.0;
        $totalPart = 0.0;

        foreach ($this->getItemsPenagihan() as $item) {
            $subtotal = (float) $item['subtotal'];
            if ($item['tipe'] === 'jasa') {
                $totalJasa += $subtotal;
            } else {
                $totalPart += $subtotal;
            }

            $rincian[] = [
                'tipe' => $item['tipe'],
                'nama' => ($item['tipe'] === 'jasa' ? '[Jasa] ' : '[Part] ').$item['nama'],
                'qty' => (int) $item['qty'],
                'harga' => (float) $item['harga'],
                'subtotal' => $subtotal,
                'sumber' => $item['sumber'],
            ];
        }

        $itemDiluarEstimasi = $this->getItemDiluarEstimasi();

        return [
            'items' => $rincian,
            'total_jasa' => $totalJasa,
            'total_part' => $totalPart,
            'total' => $totalJasa + $totalPart,
            'perubahan_lingkup' => $itemDiluarEstimasi !== [],
            'item_diluar_estimasi' => $itemDiluarEstimasi,
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(WebReview::class, 'tiket_servis_id');
    }
}
