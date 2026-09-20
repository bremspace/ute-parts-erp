<?php

namespace App\Modules\Servis\Services;

use App\Models\User;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServisService
{
    public function __construct(
        protected NotificationService $notifService
    ) {}

    /**
     * [SERVICE-01] Terima unit servis baru.
     */
    public function terimaUnit(array $data, User $user): TiketServis
    {
        $cabangId = $data['cabang_id'] ?? session('cabang_id') ?? $user->cabangs()->first()?->id;
        if (! $cabangId) {
            throw new \Exception('Cabang aktif belum dipilih');
        }

        $today = now()->format('Ymd');
        $count = TiketServis::whereDate('created_at', now()->toDateString())
            ->where('cabang_id', $cabangId)
            ->count() + 1;
        $noTiket = sprintf('SRV-C%02d-%s-%04d', $cabangId, $today, $count);

        $tiket = TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $cabangId,
            'jenis_servis_id' => $data['jenis_servis_id'] ?? null,
            'pelanggan_id' => $data['pelanggan_id'] ?? null,
            'nama_pelanggan' => $data['nama_pelanggan'] ?? null,
            'telepon_pelanggan' => $data['telepon_pelanggan'] ?? null,
            'jenis_hp' => $data['jenis_hp'],
            'seri_hp' => $data['seri_hp'] ?? null,
            // [T-19] kunci gadget: terenkripsi at-rest (cast encrypted)
            'tipe_kunci' => $data['tipe_kunci'] ?? null,
            'kunci_terenkripsi' => $data['kunci_terenkripsi'] ?? null,
            'keluhan' => $data['keluhan'],
            'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
            'foto_unit' => $data['foto_unit'] ?? null,
            'status' => 'diterima',
            'sumber' => $data['sumber'] ?? 'walkin',
            'tanggal_terima' => now(),
        ]);

        $this->logStatus($tiket, null, 'diterima', $user, 'transisi', 'Unit servis diterima');

        return $tiket;
    }

    /**
     * [SERVICE-07] Booking servis dari marketplace (public, no auth).
     */
    public function bookingOnline(array $data): TiketServis
    {
        $cabangId = $data['cabang_id'] ?? Cabang::where('is_active', true)->first()?->id;

        $today = now()->format('Ymd');
        $count = TiketServis::whereDate('created_at', now()->toDateString())->count() + 1;
        $noTiket = sprintf('SRV-ONL-%s-%04d', $today, $count);

        $tiket = TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $cabangId,
            'pelanggan_id' => $data['pelanggan_id'] ?? null,
            'nama_pelanggan' => $data['nama'] ?? null,
            'telepon_pelanggan' => $data['telepon'] ?? null,
            'jenis_hp' => $data['jenis_hp'],
            'seri_hp' => $data['seri_hp'] ?? null,
            'keluhan' => $data['keluhan'],
            'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
            'foto_unit' => $data['foto_unit'] ?? null,
            'status' => 'diajukan_online',
            'sumber' => 'online',
        ]);

        $this->logStatus($tiket, null, 'diajukan_online', null, 'transisi', 'Booking servis online');

        // Notifikasi ke admin cabang (queued)
        $this->notifService->kirim(
            'inapp', null,
            'Servis Online Baru',
            "Booking servis dari {$tiket->nama_pelanggan} untuk {$tiket->jenis_hp} — menunggu konfirmasi diterima",
            ['tiket_id' => $tiket->id]
        );

        return $tiket;
    }

    /**
     * [SERVICE-03] Update status tiket (state machine enforced).
     * $user nullable — untuk approval publik via token (tanpa login).
     */
    public function updateStatus(TiketServis $tiket, string $statusBaru, ?User $user = null, string $alasan = ''): TiketServis
    {
        $statusLama = $tiket->status;

        // Validasi transisi forward
        if (! ServisStateMachine::dapatTransisi($statusLama, $statusBaru)) {
            if (! $user || ! $user->can('servis.override-status')) {
                throw new \Exception(
                    "Transisi dari '{$statusLama}' ke '{$statusBaru}' tidak valid. Hanya admin dengan override yang diizinkan."
                );
            }
            $aksi = 'override';
        } else {
            $aksi = 'transisi';
        }

        $tiket->update(['status' => $statusBaru]);
        $this->logStatus($tiket, $statusLama, $statusBaru, $user, $aksi, $alasan);

        // Side effects per status
        match ($statusBaru) {
            'menunggu_approval' => $this->onMenungguApproval($tiket),
            'diterima' => $this->onDiterima($tiket),
            'selesai' => $this->onSelesai($tiket),
            'diambil' => $this->onDiambil($tiket),
            default => null,
        };

        // Notifikasi ke pelanggan
        $this->notifService->kirim('inapp', null,
            'Status Servis Diperbarui',
            "Tiket {$tiket->no_tiket} berubah status ke: {$statusBaru}",
            ['tiket_id' => $tiket->id, 'status' => $statusBaru]
        );

        return $tiket;
    }

    /**
     * [SERVICE-05] Input estimasi biaya → status menunggu_approval.
     */
    public function setEstimasi(TiketServis $tiket, float $biaya, string $alasan, User $user): TiketServis
    {
        $validForEstimate = ['diagnosa', 'ditolak'];
        if (! in_array($tiket->status, $validForEstimate, true)) {
            throw new \Exception("Hanya tiket berstatus 'diagnosa' atau 'ditolak' yang dapat diestimasi");
        }

        $tiket->update([
            'estimasi_biaya' => $biaya,
            'alasan_estimasi' => $alasan,
            'token_approval' => Str::random(64),
            'status' => 'menunggu_approval',
        ]);

        $this->logStatus($tiket, $tiket->status, 'menunggu_approval', $user, 'transisi', "Estimasi biaya: Rp {$biaya}");

        // Notifikasi + link publik approval
        $token = $tiket->token_approval;
        $linkApprove = url("/api/servis/public/approve/{$token}");
        $this->notifService->kirim(
            'inapp', null,
            'Estimasi Servis Perlu Approval',
            'Estimasi Rp '.number_format($biaya, 0, ',', '.')." untuk {$tiket->jenis_hp}. Approve: {$linkApprove}",
            ['tiket_id' => $tiket->id, 'token' => $token]
        );

        return $tiket;
    }

    /**
     * [SERVICE-06] Publik approve/reject (token auth).
     */
    public function approveByToken(string $token, string $action, string $alasan = ''): TiketServis
    {
        $tiket = TiketServis::where('token_approval', $token)->firstOrFail();

        if ($tiket->status !== 'menunggu_approval') {
            throw new \Exception('Tiket tidak dalam status menunggu approval');
        }

        if ($action === 'approve') {
            return $this->updateStatus($tiket, 'disetujui', null, $alasan);
        } else {
            return $this->updateStatus($tiket, 'ditolak', null, $alasan ?: 'Estimasi ditolak oleh pelanggan');
        }
    }

    /**
     * Input sparepart terpakai → kurangi stok + catat log.
     */
    public function inputSparepart(TiketServis $tiket, array $items, int $gudangId, User $user): array
    {
        if (! in_array($tiket->status, ['diagnosa', 'dikerjakan', 'disetujui'], true)) {
            throw new \Exception('Input sparepart hanya dapat dilakukan saat status diagnosa, disetujui, atau dikerjakan');
        }

        // [T-17] Anti dobel potong stok: jalur legacy hanya boleh dipakai bila belum ada
        // item part via form pekerjaan (TiketServisItem). Bila sudah — tolak.
        if (TiketServisItem::where('tiket_servis_id', $tiket->id)->where('tipe', 'part')->exists()) {
            throw new \Exception('Item sudah dicatat via form pekerjaan');
        }

        $created = [];

        DB::transaction(function () use ($tiket, $items, $gudangId, $user, &$created) {
            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (int) $item['jumlah'];

                // Deduct stock
                $stok = StokItem::where('produk_id', $produk->id)
                    ->where('gudang_id', $gudangId)
                    ->where('sku_variant_id', $item['sku_variant_id'] ?? null)
                    ->first();

                if (! $stok || $stok->jumlah < $qty) {
                    $tersedia = $stok ? $stok->jumlah : 0;
                    throw new \Exception("Stok sparepart {$produk->nama} tidak mencukupi (tersedia: {$tersedia})");
                }

                $sebelum = $stok->jumlah;
                $setelah = $sebelum - $qty;
                $stok->update(['jumlah' => $setelah]);

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'user_id' => $user->id,
                    'jenis' => 'servis',
                    'referensi_tipe' => TiketServis::class,
                    'referensi_id' => $tiket->id,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => -$qty,
                    'jumlah_setelah' => $setelah,
                    'catatan' => "Servis {$tiket->no_tiket} — sparepart terpakai",
                ]);

                $sparepart = ServisSparepart::create([
                    'tiket_servis_id' => $tiket->id,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'gudang_id' => $gudangId,
                    'jumlah' => $qty,
                    'harga_satuan' => $item['harga_satuan'] ?? (float) $produk->harga_jual_retail,
                    'hpp' => (float) $produk->harga_beli,
                ]);

                $created[] = $sparepart;
            }
        });

        return $created;
    }

    /**
     * [T-17] Input pekerjaan teknisi — split part & jasa via TiketServisItem.
     * Baris tipe=jasa: tidak menyentuh stok. Baris tipe=part: kurangi StokItem 1x + StokLog.
     *
     * @param  array  $items  [['tipe'=>'part|jasa','produk_id'=>?,'sku_variant_id'=>?,'nama_item'=>string,'qty'=>int,'harga'=>float,'gudang_id'=>(wajib utk part)], ...]
     */
    public function inputPekerjaan(TiketServis $tiket, array $items, User $user): array
    {
        if (! in_array($tiket->status, ['disetujui', 'dikerjakan', 'qc'], true)) {
            throw new \Exception('Input pekerjaan hanya saat status disetujui / dikerjakan / qc');
        }

        $created = [];

        DB::transaction(function () use ($tiket, $items, $user, &$created) {
            foreach ($items as $item) {
                $tipe = $item['tipe'] ?? 'jasa';
                $namaItem = $item['nama_item'];
                $qty = (int) ($item['qty'] ?? 1);
                $harga = (float) ($item['harga'] ?? 0);

                if ($tipe === 'part') {
                    $produkId = $item['produk_id'] ?? null;
                    if (! $produkId) {
                        throw new \Exception("Item part '{$namaItem}' wajib pilih produk");
                    }
                    $produk = Produk::findOrFail($produkId);
                    $gudangId = $item['gudang_id'] ?? null;
                    if (! $gudangId) {
                        throw new \Exception("Part '{$namaItem}' wajib pilih gudang");
                    }

                    // Deduct stok 1x (fee guard: lockForUpdate di StokDeductionService reklarasi)
                    $this->kurangiStokServis($produk->id, $item['sku_variant_id'] ?? null, $gudangId, $qty, $tiket, $user);
                }

                $created[] = TiketServisItem::create([
                    'tiket_servis_id' => $tiket->id,
                    'tipe' => $tipe,
                    'produk_id' => $tipe === 'part' ? ($item['produk_id'] ?? null) : null,
                    'sku_variant_id' => $tipe === 'part' ? ($item['sku_variant_id'] ?? null) : null,
                    'nama_item' => $namaItem,
                    'qty' => $qty,
                    'harga' => $harga,
                    'hpp' => $tipe === 'part' && ($item['produk_id'] ?? null)
                        ? (float) (Produk::find($item['produk_id'])?->harga_beli ?? 0)
                        : 0,
                ]);
            }
        });

        return $created;
    }

    private function kurangiStokServis(int $produkId, ?int $variantId, int $gudangId, int $qty, TiketServis $tiket, User $user): void
    {
        $stok = StokItem::where('produk_id', $produkId)
            ->where('sku_variant_id', $variantId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        if (! $stok || $stok->jumlah < $qty) {
            $tersedia = $stok ? $stok->jumlah : 0;
            throw new \Exception("Stok sparepart tidak mencukupi di gudang terpilih (tersedia: {$tersedia})");
        }

        $sebelum = $stok->jumlah;
        $stok->update(['jumlah' => $sebelum - $qty]);

        // [T-26] SOT mutation log
        StockMutationLog::create([
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'gudang_id' => $gudangId,
            'delta' => -$qty,
            'sumber' => 'servis',
            'referensi_tipe' => TiketServis::class,
            'referensi_id' => $tiket->id,
            'terjadi_at' => now(),
        ]);

        StokLog::create([
            'gudang_id' => $gudangId,
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'user_id' => $user->id,
            'jenis' => 'servis',
            'referensi_tipe' => TiketServis::class,
            'referensi_id' => $tiket->id,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => -$qty,
            'jumlah_setelah' => $sebelum - $qty,
            'catatan' => "Servis {$tiket->no_tiket} — item part",
        ]);
    }

    /**
     * Get tiket by token_approval (public tracking).
     */
    public function getByToken(string $token): TiketServis
    {
        return TiketServis::with(['garansi', 'spareparts.produk', 'jenisservis', 'pelanggan'])
            ->where('token_approval', $token)
            ->firstOrFail();
    }

    // --- Private side-effect methods ---

    private function onMenungguApproval(TiketServis $tiket): void
    {
        if (! $tiket->token_approval) {
            $tiket->update(['token_approval' => Str::random(64)]);
        }
    }

    private function onDiterima(TiketServis $tiket): void
    {
        if (! $tiket->tanggal_terima) {
            $tiket->update(['tanggal_terima' => now()]);
        }
    }

    private function onSelesai(TiketServis $tiket): void
    {
        $tiket->update(['tanggal_selesai' => now()]);

        // Auto-create garansi dari konfigurasi jenis servis
        $durasiHari = 30; // default
        if ($tiket->jenisServis) {
            $durasiHari = $tiket->jenisServis->durasi_garansi_hari;
        }

        $tanggalSelesai = now()->toDateTimeString();
        Garansi::updateOrCreate(
            ['tiket_servis_id' => $tiket->id],
            [
                'durasi_hari' => $durasiHari,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_berakhir' => now()->addDays($durasiHari)->toDateString(),
                'keterangan' => "Garansi {$durasiHari} hari — Servis {$tiket->no_tiket}",
            ]
        );

        // Jurnal akuntansi otomatis (PRD §4.6):
        // - Pendapatan Jasa Servis (420-01) kredit = estimasi biaya
        // - HPP sparepart terpakai (510-02) debit, Persediaan (130-01) kredit
        // - Kas (110-01) debit = total tagihan (jasa + sparepart)
        try {
            $jurnalService = app(JurnalService::class);

            $jasaServis = (float) ($tiket->estimasi_biaya ?? 0);

            // [T-17] Dasar perhitungan dari TiketServisItem (part/jasa) bila ada;
            // fallback ke spareparts legacy utk kompatibilitas.
            $itemsServis = $tiket->items()->get();
            if ($itemsServis->isNotEmpty()) {
                $pendapatanJasa = (float) $itemsServis->where('tipe', 'jasa')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
                $pendapatanPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
                $hppPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->hpp * (int) $i->qty);

                $totalHpp = $hppPart;
                $totalJualSparepart = $pendapatanPart;
                // jasa dari items lebih akurat dari estimasi jika diisi
                if ($pendapatanJasa > 0) {
                    $jasaServis = $pendapatanJasa;
                }
            } else {
                // HPP sparepart (biaya perolehan)
                $totalHpp = (float) $tiket->spareparts()
                    ->get()
                    ->sum(fn ($sp) => (float) $sp->hpp * (int) $sp->jumlah);
                // Nilai jual sparepart (pendapatan penjualan sparepart)
                $totalJualSparepart = (float) $tiket->spareparts()
                    ->get()
                    ->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);
            }

            $totalTagihan = $jasaServis + $totalJualSparepart;

            if ($totalTagihan > 0) {
                $noJurnal = $jurnalService->generateNoJurnal('servis', $tiket->cabang_id);

                $lines = [
                    ['akun_kode' => '110-01', 'debit' => $totalTagihan, 'kredit' => 0], // Kas masuk (jasa + sparepart jual)
                    ['akun_kode' => '420-01', 'debit' => 0, 'kredit' => $jasaServis], // Pendapatan jasa servis
                ];

                if ($totalJualSparepart > 0) {
                    $lines[] = ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $totalJualSparepart]; // Pendapatan penjualan sparepart
                }
                if ($totalHpp > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0]; // HPP sparepart
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp]; // Persediaan turun (biaya perolehan)
                }

                $jurnalService->post(
                    $noJurnal,
                    now(),
                    'servis',
                    $lines,
                    "Jurnal servis {$tiket->no_tiket}",
                    $tiket->cabang_id,
                    auth()->id(),
                    TiketServis::class,
                    $tiket->id
                );

                // Komisi reseller: jika servis milik reseller, hitung komisi
                if ($tiket->pelanggan?->is_reseller) {
                    $komisiService = app(KomisiService::class);
                    $komisiService->hitungKomisiDariItems(
                        $tiket->pelanggan,
                        [
                            [
                                'kategori' => 'Servis',
                                'subtotal' => $jasaServis,
                                'jumlah' => 1,
                            ],
                        ],
                        [
                            'jumlah_transaksi' => $totalTagihan,
                            'keterangan' => 'Komisi dari servis '.$tiket->no_tiket,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            // Jangan blokir selesai servis jika jurnal gagal — log & lanjut
            // (COA harus ter-seed; kegagalan dicatat agar bisa diperbaiki)
            Log::warning("Jurnal otomatis servis gagal: {$e->getMessage()}", [
                'tiket' => $tiket->no_tiket,
            ]);
        }
    }

    private function onDiambil(TiketServis $tiket): void
    {
        $tiket->update(['tanggal_diambil' => now()]);
    }

    private function logStatus(
        TiketServis $tiket,
        ?string $dari,
        string $ke,
        ?User $user,
        string $aksi,
        string $alasan
    ): void {
        ServisStatusLog::create([
            'tiket_servis_id' => $tiket->id,
            'status_dari' => $dari,
            'status_ke' => $ke,
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'alasan' => $alasan,
        ]);

        // Audit: override status wajib tercatat (PRD §6)
        if ($aksi === 'override') {
            app(AuditService::class)->catat(
                'TiketServis', 'override', $tiket->id,
                "Override status {$dari} → {$ke} pada {$tiket->no_tiket}: {$alasan}",
                ['status' => $dari], ['status' => $ke]
            );
        }
    }
}
