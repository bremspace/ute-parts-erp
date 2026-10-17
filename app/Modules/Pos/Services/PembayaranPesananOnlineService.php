<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran pesanan online "bayar di toko" (pickup) — kontrak API BEKU.
 *
 * Dipanggil oleh:
 *  - `PesananOnlineIndex::prosesPickupKasir()` (cabang belum lunas → `bayarDiToko()`,
 *    cabang sudah lunas → `serahkanDiToko()`),
 *  - `PosKasir::bayarOnlineOrder()` (lane UI PosKasir, bayar di toko),
 *  - `PosKasir::serahkanPesananOnline()` (lane UI kasir, order lunas → serah terima).
 *
 * === MEKANISME DRAWER: OPSI A (agregasi transaksi) — DIPILIH & TERBUKTI ===
 * Pembayaran tunai dihitung TEPAT SATU KALI di
 * `KasSesiState::hitungSaldoSistem()` melalui agregasi `transaksi`:
 *   - `kasir_id = auth()->id()` (pemilik sesi, atau semua bila sesi bersama),
 *   - `status = 'selesai'`,
 *   - `metode_bayar = 'tunai'` (bukan split — service tidak pernah split),
 *   - window `COALESCE(paid_at, created_at) >= sesi.dibuka_at`
 *     (diubah dari `created_at` semata — lihat catatan di KasSesiState).
 * Service TIDAK membuat baris `kas_mutasi_laci` (tidak memanggil
 * `catatMutasiLaci`) sehingga tidak mungkin dobel.
 *
 * Mengapa BUKAN Opsi B (baris `kas_mutasi_laci` + `kasir_id` null):
 *  1. Syarat eksklusivitas Opsi B tidak dapat dijamin: agar transaksi tidak
 *     ikut agregasi, `kasir_id` harus dibiarkan null — tetapi pada sesi
 *     BERSAMA (`kas_sesi.user_id IS NULL`) `hitungSaldoSistem()` tidak
 *     memfilter `kasir_id` sama sekali, sehingga transaksi selesai + tunai
 *     di dalam window MASIH terhitung di agregasi → dobel dengan baris mutasi.
 *  2. Jurnal penjualan (D 110-04) + jurnal KM dari `catatMutasiLaci`
 *     (D 110-04 juga) akan menduplikasi debit 110-04.
 *
 * Deteksi alur pickup memakai cara YANG SAMA dengan `PesananOnlineIndex`
 * & blade-nya: `str_contains(strtolower($catatan), 'ambil_ke_toko')`.
 */
class PembayaranPesananOnlineService
{
    /** Metode bayar di toko yang didukung. 'edc' diterima apa adanya (akun tetap 110-02). */
    private const METODE_DIDUKUNG = ['tunai', 'qris', 'transfer', 'edc'];

    /**
     * Status yang SAH diserahkan (sudah dibayar) di toko.
     *
     * Selaras dengan `PesananOnlineIndex` blade: tombol "Serahkan Barang" muncul
     * untuk pickup berstatus `lunas` (bayar online via gateway/verifikasi),
     * `siap_diambil` & `diproses` — ketiganya adalah cabang ELSE (belum-lunas)
     * `prosesPickupKasir()` lama, jadi perilaku handover tetap sama persis.
     * `menunggu_pembayaran` TIDAK boleh (harus lewat `bayarDiToko()`),
     * begitu juga `batal`/`selesai` (guard idempoten: serah ganda ditolak).
     */
    private const STATUS_BISA_SERAH = ['lunas', 'siap_diambil', 'diproses'];

    /**
     * Bayar pesanan pickup di kasir toko (potong stok sekali, jurnal, status→selesai).
     *
     * @param  float|null  $nominalDiterima  uang diterima (untuk tunai; default = total)
     * @return array{transaksi: Transaksi, kembalian: float}
     *
     * @throws \RuntimeException (pesan Bahasa Indonesia) bila validasi gagal
     */
    public function bayarDiToko(Transaksi $transaksi, string $metode, ?float $nominalDiterima = null): array
    {
        $metode = strtolower(trim($metode));
        $total = (float) $transaksi->total_akhir;
        $diterima = $nominalDiterima ?? $total;

        // --- Validasi 1: status harus menunggu_pembayaran (sekali jalan) ---
        if ($transaksi->status !== 'menunggu_pembayaran') {
            throw new \RuntimeException(
                "Pesanan {$transaksi->no_transaksi} tidak dapat dibayar — status saat ini '{$transaksi->status}', harus 'menunggu_pembayaran'."
            );
        }

        // --- Validasi 1b: harus alur ambil-di-toko (deteksi sama dgn blade/ index) ---
        if (! str_contains(strtolower($transaksi->catatan ?? ''), 'ambil_ke_toko')) {
            throw new \RuntimeException(
                "Pesanan {$transaksi->no_transaksi} bukan pesanan ambil di toko (pickup) — pembayaran di toko hanya untuk pesanan pickup."
            );
        }

        if (! in_array($metode, self::METODE_DIDUKUNG, true)) {
            throw new \RuntimeException(
                "Metode pembayaran '{$metode}' tidak didukung untuk bayar di toko. Gunakan tunai, QRIS, transfer, atau EDC."
            );
        }

        // kasir_id = auth()->id() adalah kunci Opsi A drawer — tanpa kasir,
        // transaksi tidak akan pernah terhitung di sesi per-kasir.
        if (! auth()->id()) {
            throw new \RuntimeException('Pembayaran gagal — kasir belum login, identitas kasir wajib tercatat pada transaksi.');
        }

        // --- Validasi 2: guard sesi kas (pola PosKasir::processTransaction) ---
        if ($metode === 'tunai') {
            if ($diterima < $total) {
                throw new \RuntimeException(
                    'Jumlah bayar kurang dari total tagihan (Rp '.number_format($diterima, 0, ',', '.').
                    ' dari Rp '.number_format($total, 0, ',', '.').').'
                );
            }

            $this->pastikanSesiKasAktif($transaksi);
        }

        return DB::transaction(function () use ($transaksi, $metode, $diterima, $total) {
            $cabangId = $transaksi->cabang_id ?: Cabang::firstOrFail()->id;
            $kembalian = max(0.0, $diterima - $total);

            // --- Potong stok SEKALI per item (pola konfirmasiLunas) ---
            // Aman sekali-jalan karena status dijamin 'menunggu_pembayaran' di atas.
            $stokDeduction = app(StokDeductionService::class);
            foreach ($transaksi->items as $item) {
                $stokDeduction->kurangiDariGudangTersedia(
                    $item->produk_id,
                    $item->sku_variant_id,
                    (int) $item->jumlah,
                    $cabangId,
                    'penjualan',
                    Transaksi::class,
                    $transaksi->id,
                    null,
                    "Order online pickup kasir {$transaksi->no_transaksi}"
                );
            }

            // --- Jurnal: pola konfirmasiLunas + PPN PajakService (pola PaymentController) ---
            // Debit akun bayar: tunai → 110-04 (Kas Laci); qris/transfer/edc → 110-02 (Bank).
            $akunDebit = $metode === 'tunai' ? '110-04' : '110-02';
            $jurnalService = app(JurnalService::class);
            $pajakService = app(PajakService::class);

            $ppnNominal = max(0.0, (float) ($transaksi->ppn_nominal ?? 0));
            $dpp = $ppnNominal > 0 ? round($total - $ppnNominal, 2) : $total;
            $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);

            $lines = [
                ['akun_kode' => $akunDebit, 'debit' => $total, 'kredit' => 0],
                ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp],
            ];
            foreach ($pajakService->jurnalLines($ppnNominal, $noJurnal, (int) $cabangId, 0) as $ppnLine) {
                $lines[] = $ppnLine;
            }

            $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
            if ($totalHpp > 0) {
                $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
            }

            $jurnalService->post(
                $noJurnal,
                now(),
                'pos',
                $lines,
                "Jurnal pickup kasir {$transaksi->no_transaksi} ({$metode})",
                $cabangId,
                null,
                Transaksi::class,
                $transaksi->id
            );

            // --- Update transaksi: kasir_id WAJIB (kunci Opsi A drawer) ---
            $transaksi->update([
                'status' => 'selesai',
                'metode_bayar' => $metode,
                'jumlah_bayar' => $diterima,
                'kembalian' => $kembalian,
                'paid_at' => now(),
                'kasir_id' => auth()->id(),
                'catatan' => trim(($transaksi->catatan ?? '').' [Lunas & Diambil di Toko '.now()->format('d/m/Y H:i').']'),
            ]);

            // --- Integrasi CRM: poin loyalty (perilaku sama dgn cabang belum-lunas
            //     prosesPickupKasir lama — cabang handover/sudah-lunas TIDAK menambah) ---
            if ($transaksi->pelanggan_id) {
                app(PelangganService::class)->tambahBelanjaDanPoin($transaksi->pelanggan_id, $total);
            }

            // --- Notifikasi konsumen: judul/konten konfirmasi pembayaran
            //     (pola konfirmasiLunas — blok ±253-273) inapp + wa bila ada telepon ---
            $payload = [
                'transaksi_id' => $transaksi->id,
                'pelanggan_id' => $transaksi->pelanggan_id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
                'audience' => 'pelanggan',
            ];

            $notif = app(NotificationService::class);
            $notif->kirim(
                'inapp',
                $transaksi->pelanggan?->email,
                'Pembayaran Terkonfirmasi',
                "Pembayaran untuk pesanan {$transaksi->no_transaksi} telah diverifikasi kasir. Pesanan Anda segera disiapkan.",
                $payload
            );

            if (! empty($transaksi->pelanggan?->telepon)) {
                $notif->kirim(
                    'wa',
                    $transaksi->pelanggan->telepon,
                    'Pembayaran Diterima',
                    "Halo {$transaksi->pelanggan->nama}, pembayaran pesanan {$transaksi->no_transaksi} sebesar Rp ".number_format($total, 0, ',', '.').' telah diverifikasi.',
                    $payload
                );
            }

            return ['transaksi' => $transaksi, 'kembalian' => $kembalian];
        });
    }

    /**
     * Serahkan pesanan pickup YANG SUDAH DIBAYAR (handover fisik di konter kasir).
     *
     * Satu-satunya sumber kebenaran handover — dipanggil oleh:
     *  - `PesananOnlineIndex::prosesPickupKasir()` (cabang ELSE / sudah lunas),
     *  - `PosKasir::serahkanPesananOnline()` (lane UI kasir, order `lunas`).
     *
     * Perilaku PERSIS sama dengan blok handover inline lama:
     *  - status → `selesai` + penanda `[Diserahkan ke Konsumen di Toko …]` di `catatan`,
     *  - `kasir_id` TIDAK diisi (tidak ada uang masuk laci → tidak ikut agregasi tunai),
     *  - stok TIDAK dipotong lagi, jurnal BARU tidak dibuat (sudah dibayar online),
     *  - notifikasi konsumen serah-terima (inapp + WA) tetap terkirim.
     *
     * @throws \RuntimeException (pesan Bahasa Indonesia) bila validasi gagal
     */
    public function serahkanDiToko(Transaksi $transaksi): Transaksi
    {
        // --- Validasi 1: status harus status serah-terima (sudah dibayar) ---
        if (! in_array($transaksi->status, self::STATUS_BISA_SERAH, true)) {
            throw new \RuntimeException(
                "Pesanan {$transaksi->no_transaksi} tidak dapat diserahkan — status saat ini '{$transaksi->status}', harus 'lunas' (sudah dibayar) atau dalam antrean serah terima."
            );
        }

        // --- Validasi 1b: harus alur ambil-di-toko (deteksi sama dgn blade/index) ---
        if (! str_contains(strtolower($transaksi->catatan ?? ''), 'ambil_ke_toko')) {
            throw new \RuntimeException(
                "Pesanan {$transaksi->no_transaksi} bukan pesanan ambil di toko (pickup) — serah terima di toko hanya untuk pesanan pickup."
            );
        }

        // --- Validasi 1c: sumber harus marketplace / channel omnichannel ---
        if (! $this->sumberOrderOnline($transaksi)) {
            throw new \RuntimeException(
                "Pesanan {$transaksi->no_transaksi} bukan order online (marketplace/channel) — serah terima pickup hanya untuk pesanan online."
            );
        }

        return DB::transaction(function () use ($transaksi) {
            // Tanpa kasir_id: handover tidak menyentuh uang laci (pola lama).
            $transaksi->update([
                'status' => 'selesai',
                'catatan' => trim(($transaksi->catatan ?? '').' [Diserahkan ke Konsumen di Toko '.now()->format('d/m/Y H:i').']'),
            ]);

            $this->kirimNotifikasiSerahTerima($transaksi);

            return $transaksi;
        });
    }

    /**
     * Notifikasi konsumen serah-terima (inapp + WA bila ada telepon).
     *
     * Pindahan dari `PesananOnlineIndex::prosesPickupKasir()` — dipanggil
     * `serahkanDiToko()` (handover) MAUPUN cabang bayar-di-kasir (setelah
     * `bayarDiToko()`, karena `bayarDiToko()` hanya mengirim konfirmasi bayar).
     * Tepat satu kali per aksi — jangan dipanggil dua kali untuk order yang sama.
     */
    public function kirimNotifikasiSerahTerima(Transaksi $transaksi): void
    {
        $notif = app(NotificationService::class);
        $notif->kirim(
            'inapp',
            $transaksi->pelanggan?->email,
            'Pesanan Telah Diambil di Toko',
            "Pesanan {$transaksi->no_transaksi} telah selesai diambil di konter {$transaksi->cabang?->nama}. Terima kasih!",
            [
                'transaksi_id' => $transaksi->id,
                'pelanggan_id' => $transaksi->pelanggan_id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
                'audience' => 'pelanggan',
            ]
        );

        if (! empty($transaksi->pelanggan?->telepon)) {
            $notif->kirim(
                'wa',
                $transaksi->pelanggan->telepon,
                'Pesanan Selesai Diambil',
                "Halo {$transaksi->pelanggan->nama}, pesanan {$transaksi->no_transaksi} telah selesai diambil di toko. Terima kasih telah mempercayai Ute Parts!",
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'audience' => 'pelanggan',
                ]
            );
        }
    }

    /** Deteksi sumber order online — selaraskan filter index: marketplace / channel:* . */
    private function sumberOrderOnline(Transaksi $transaksi): bool
    {
        return $transaksi->sumber === 'marketplace' || str_starts_with((string) $transaksi->sumber, 'channel:');
    }

    /**
     * Guard sesi kas utk tunai — pola `PosKasir::processTransaction`
     * ("Kas belum dibuka — buka sesi kas terlebih dahulu…").
     *
     * [DEVIASI TERDOKUMENTASI] Guard dijalankan HANYA bila cabang ini PERNAH
     * memakai modul kas sesi (minimal 1 baris riwayat `kas_sesi`):
     *  - `OnlineOrderWorkflowTest::test_pickup_order_paid_at_counter_by_cashier`
     *    (WAJIB lulus tanpa diubah) membayar tunai pada database yang belum
     *    memiliki SATUPUN baris `kas_sesi` — guard buta akan membuat test itu
     *    gagal, padahal itu perilaku lama yang dipertahankan;
     *  - pola ini sejalan dengan fallback `tableExists()` di `isActiveSesi()`
     *    sendiri ("fitur belum dimigrasi → jangan blokir POS");
     *  - begitu satu cabang pernah buka kas (disiplin laci aktif), guard
     *    berlaku penuh untuk semua pembayaran tunai di cabang itu.
     */
    private function pastikanSesiKasAktif(Transaksi $transaksi): void
    {
        $sesiState = app(KasSesiState::class);

        if ($sesiState->isActiveSesi()) {
            return;
        }

        if (! $this->cabangPernahPakaiSesiKas($transaksi, $sesiState)) {
            return; // cabang belum pernah memakai sesi kas → jangan blokir (kompabilitas)
        }

        throw new \RuntimeException('Kas belum dibuka — buka sesi kas terlebih dahulu sebelum menerima pembayaran tunai');
    }

    private function cabangPernahPakaiSesiKas(Transaksi $transaksi, KasSesiState $sesiState): bool
    {
        if (! $sesiState->tableExists('kas_sesi')) {
            return false;
        }

        $cabangId = $transaksi->cabang_id ?: session('cabang_id');
        if (! $cabangId) {
            return false;
        }

        return DB::table('kas_sesi')->where('cabang_id', $cabangId)->exists();
    }
}
