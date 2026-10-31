<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pemrosesan hasil pembayaran Duitku (lunas/gagal/kadaluarsa) — DIPAKAI BERSAMA
 * oleh webhook PAY-02 (PaymentController) dan command `duitku:rekon` (T-03).
 *
 * Dipindahkan utuh dari PaymentController (B-10b/P0-5) TANPA mengubah logika
 * inti: idempotency (STATUS_FINAL + lockForUpdate), validasi nominal
 * (toleransi 1 rupiah), stok, jurnal (DPP/PPN/HPP), komisi multi-aktor,
 * notifikasi via queue (tidak pernah sync).
 */
class DuitkuPaymentProcessor
{
    /**
     * [B-10b/P0-5] Toleransi pencocokan nominal (rupiah bulat).
     * Duitku mengirim amount integer; total_akhir desimal 15,2 → selisih
     * pembulatan 1 rupiah masih dianggap cocok, selebihnya = tidak cocok.
     */
    public const TOLERANSI_AMOUNT = 1;

    /**
     * [B-10b/P0-5] Status final order = callback-nya sudah pernah diproses.
     * Eksplisit (bukan hanya 'lunas') supaya callback gagal/pending yang
     * terlambat tidak menimpa order yang sudah dibayar.
     */
    public const STATUS_FINAL = ['selesai', 'lunas', 'dibatalkan', 'batal', 'kadaluarsa', 'gagal'];

    public function __construct(
        protected JurnalService $jurnalService,
        protected PajakService $pajakService,
        protected StokDeductionService $stokDeduction,
        protected KomisiService $komisiService,
        protected NotificationService $notifService
    ) {}

    /**
     * Callback sukses (resultCode 00 / transactionStatus statusCode 00):
     * order → lunas dalam satu transaksi atomik + row lock.
     * Idempoten: order yang statusnya sudah final dikembalikan sukses TANPA
     * potong stok / jurnal / notifikasi lagi.
     *
     * @param  string|null  $reference  reference Duitku dari callback — disimpan
     *                                  ke payment_reference bila kosong/beda.
     * @return array{kode: string, status?: string, expected?: int, diterima?: int, error?: string}
     */
    public function prosesLunas(string $merchantOrderId, int $amount, ?string $reference = null, ?string $metodeBayar = null): array
    {
        try {
            return DB::transaction(function () use ($merchantOrderId, $amount, $reference, $metodeBayar) {
                // lockForUpdate() → baris order terkunci sampai commit; webhook
                // paralel untuk order yang sama akan MENUNGGU di titik ini lalu
                // membaca status 'lunas' (idempotent skip), bukan lolos guard.
                $transaksi = Transaksi::with(['items.produk', 'pelanggan.tierMembership'])
                    ->where('no_transaksi', $merchantOrderId)
                    ->lockForUpdate()
                    ->first();

                if (! $transaksi) {
                    return ['kode' => 'tidak_ditemukan'];
                }

                // ===== IDEMPOTENCY GUARD (dalam lock) =====
                if (in_array($transaksi->status, self::STATUS_FINAL, true)) {
                    return ['kode' => 'sudah_diproses', 'status' => $transaksi->status];
                }

                // ===== Validasi nominal =====
                $expected = (int) round((float) $transaksi->total_akhir);
                if (abs($amount - $expected) > self::TOLERANSI_AMOUNT) {
                    // TIDAK proses apa pun (stok, jurnal, status) — order tetap
                    // menunggu_pembayaran supaya bisa direkonsiliasi manual.
                    return ['kode' => 'nominal_tidak_cocok', 'expected' => $expected, 'diterima' => $amount];
                }

                // a. Update status transaksi SEBELUM operasi lain (idempotency marker).
                $channelBayar = $metodeBayar ?: 'duitku';
                $update = [
                    'status' => 'lunas',
                    'metode_bayar' => $channelBayar,
                    'jumlah_bayar' => (float) $transaksi->total_akhir,
                    'paid_at' => now(),
                ];
                // Simpan reference callback bila payment_reference kosong/beda
                if ($reference !== null && $reference !== '' && $reference !== $transaksi->payment_reference) {
                    $update['payment_reference'] = $reference;
                }
                $transaksi->update($update);

                // b. Kurangi stok per item (gudang tersedia di cabang transaksi)
                $cabangId = $transaksi->cabang_id ?: Cabang::firstOrFail()->id;
                foreach ($transaksi->items as $item) {
                    $qtyDasar = (int) round($item->jumlah * ($item->konversi ?? 1));
                    $this->stokDeduction->kurangiDariGudangTersedia(
                        $item->produk_id,
                        $item->sku_variant_id,
                        $qtyDasar,
                        $cabangId,
                        'penjualan',
                        Transaksi::class,
                        $transaksi->id,
                        null,
                        "POS Online {$merchantOrderId} (lunas ".($channelBayar === 'qris' ? 'QRIS Toko' : 'Duitku').')'
                    );
                }

                // c. Jurnal otomatis: Kas masuk / Pendapatan / PPN / HPP / Persediaan turun
                $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
                $totalAkhir = (float) $transaksi->total_akhir;
                [$dpp, $ppnNominal] = $this->pisahDppPpn($transaksi, $totalAkhir);
                $noJurnal = $this->jurnalService->generateNoJurnal('pos', $cabangId);

                $debitAkun = ($channelBayar === 'qris') ? '110-02' : '120-02';
                $lines = [
                    ['akun_kode' => $debitAkun, 'debit' => $totalAkhir, 'kredit' => 0],
                    ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp],
                ];
                // PPN Keluaran → akun 220-01 (kontrak AC F1-2, baris dari PajakService)
                foreach ($this->pajakService->jurnalLines($ppnNominal, $noJurnal, (int) $cabangId, 0) as $ppnLine) {
                    $lines[] = $ppnLine;
                }
                if ($totalHpp > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
                }
                $this->jurnalService->post(
                    $noJurnal,
                    now(),
                    'pos',
                    $lines,
                    "Jurnal order online {$merchantOrderId} (lunas ".($channelBayar === 'qris' ? 'QRIS Toko' : 'Duitku').')',
                    $cabangId,
                    null,
                    Transaksi::class,
                    $transaksi->id
                );

                // d. Komisi multi-aktor (PRD §4.3): reseller/agen/karyawan marketing
                $this->komisiService->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

                // d2. Pencatatan komisi akumulasi jika mode komisi_akumulasi aktif & belum dicatat rule engine
                if ($transaksi->mode_reward_mitra === 'komisi_akumulasi' && (float) $transaksi->komisi_mitra_nominal > 0 && $transaksi->pelanggan_id) {
                    $sudahAda = Komisi::where('transaksi_id', $transaksi->id)->where('pelanggan_id', $transaksi->pelanggan_id)->exists();
                    if (! $sudahAda) {
                        $today = now()->format('Ymd');
                        $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;
                        $noKomisi = sprintf('KMS-%s-%04d', $today, $count);
                        $tipeKonsumen = $transaksi->pelanggan ? app(PelangganService::class)->tipeKonsumen($transaksi->pelanggan) : 'reseller';
                        Komisi::create([
                            'no_komisi' => $noKomisi,
                            'pelanggan_id' => $transaksi->pelanggan_id,
                            'transaksi_id' => $transaksi->id,
                            'aktor_tipe' => $tipeKonsumen === 'agen' ? 'agen' : 'reseller',
                            'aktor_id' => $transaksi->pelanggan_id,
                            'nominal_komisi' => $transaksi->komisi_mitra_nominal,
                            'jumlah_transaksi' => $transaksi->total_akhir,
                            'status' => Komisi::STATUS_PENDING,
                            'keterangan' => 'Komisi akumulasi mitra transaksi marketplace '.$transaksi->no_transaksi,
                        ]);
                    }
                }

                // e. Notifikasi Backoffice & Pelanggan (via queue)
                $this->notifService->kirim(
                    'inapp',
                    null,
                    "Pembayaran Pesanan {$merchantOrderId} Diterima",
                    'Pesanan online '.$merchantOrderId.' lunas (Rp '.number_format($totalAkhir, 0, ',', '.').'). Segera siapkan dan kemas suku cadang.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'cabang_id' => $cabangId,
                        'target_role' => 'staff-gudang',
                        'kategori' => 'transaksi',
                        'sumber' => 'marketplace',
                        'action_url' => '/app/pos/online-orders',
                        'audience' => 'staf',
                    ]
                );

                $this->notifService->kirim(
                    'inapp',
                    $transaksi->pelanggan?->email,
                    'Pembayaran Diterima',
                    "Pembayaran pesanan {$merchantOrderId} berhasil. Tim toko sedang mempersiapkan paket Anda.",
                    [
                        'transaksi_id' => $transaksi->id,
                        'pelanggan_id' => $transaksi->pelanggan_id,
                        'kategori' => 'transaksi',
                        'sumber' => 'marketplace',
                        'action_url' => '/account',
                        'audience' => 'pelanggan',
                    ]
                );

                if ($transaksi->pelanggan?->telepon) {
                    $this->notifService->kirim(
                        'wa',
                        $transaksi->pelanggan->telepon,
                        'Pembayaran Pesanan Lunas',
                        "Terima kasih {$transaksi->pelanggan->nama}! Pembayaran pesanan {$merchantOrderId} sebesar Rp ".number_format($totalAkhir, 0, ',', '.').' telah kami terima. Pesanan Anda segera dikemas & dikirim.',
                        [
                            'transaksi_id' => $transaksi->id,
                            'pelanggan_id' => $transaksi->pelanggan_id,
                            'audience' => 'pelanggan',
                        ]
                    );
                }

                // f. Integrasi CRM: update total belanja 12 bulan & poin loyalty pelanggan
                if ($transaksi->pelanggan_id) {
                    app(PelangganService::class)->tambahBelanjaDanPoin($transaksi->pelanggan_id, $totalAkhir);
                }

                // g. Otomatis sinkronkan status notifikasi sebelumnya (selesaikan notif "menunggu bayar")
                $this->notifService->sinkronkanStatusTransaksi($transaksi, 'lunas');

                return ['kode' => 'sukses'];
            });
        } catch (\Exception $e) {
            // Rollback DB::transaction sudah mengembalikan status/stok/jurnal.
            // TIDAK perlu update status manual lagi (bisa menimpa order yang
            // bersamaan sudah 'lunas').
            Log::error('[Duitku] Process failed, transaction rolled back', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['kode' => 'proses_gagal', 'error' => $e->getMessage()];
        }
    }

    /**
     * Callback gagal/kadaluarsa (resultCode 01-05) — juga di row lock supaya
     * tidak menimpa order yang bersamaan sudah 'lunas'.
     *
     * @return array{kode: string, status?: string}
     */
    public function prosesGagal(string $merchantOrderId, string $resultCode): array
    {
        try {
            return DB::transaction(function () use ($merchantOrderId) {
                $transaksi = Transaksi::where('no_transaksi', $merchantOrderId)
                    ->lockForUpdate()
                    ->first();

                if (! $transaksi) {
                    return ['kode' => 'tidak_ditemukan'];
                }

                if (in_array($transaksi->status, self::STATUS_FINAL, true)) {
                    return ['kode' => 'sudah_diproses', 'status' => $transaksi->status];
                }

                $transaksi->update([
                    'status' => 'gagal',
                    'paid_at' => null,
                ]);

                if ($transaksi->pelanggan_id) {
                    $this->notifService->kirim(
                        'inapp', null,
                        'Pembayaran Gagal',
                        "Pesanan {$merchantOrderId} gagal dibayar. Silakan coba kembali.",
                        [
                            'transaksi_id' => $transaksi->id,
                            'pelanggan_id' => $transaksi->pelanggan_id,
                            'kategori' => 'transaksi',
                            'action_url' => '/account',
                            'audience' => 'pelanggan',
                        ]
                    );
                }

                $this->notifService->sinkronkanStatusTransaksi($transaksi, 'gagal');

                return ['kode' => 'sukses'];
            });
        } catch (\Exception $e) {
            Log::error('[Duitku] Gagal menandai order sebagai gagal', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['kode' => 'proses_gagal', 'error' => $e->getMessage()];
        }
    }

    /**
     * Tandai order kadaluarsa (dipakai `duitku:rekon`): lewat expiryPeriod dan
     * transactionStatus bukan sukses. Row lock + guard STATUS_FINAL sama seperti
     * webhook — tidak menimpa order yang baru saja lunas.
     *
     * @return array{kode: string, status?: string}
     */
    public function prosesKadaluarsa(string $merchantOrderId): array
    {
        try {
            return DB::transaction(function () use ($merchantOrderId) {
                $transaksi = Transaksi::where('no_transaksi', $merchantOrderId)
                    ->lockForUpdate()
                    ->first();

                if (! $transaksi) {
                    return ['kode' => 'tidak_ditemukan'];
                }

                if (in_array($transaksi->status, self::STATUS_FINAL, true)) {
                    return ['kode' => 'sudah_diproses', 'status' => $transaksi->status];
                }

                $transaksi->update([
                    'status' => 'kadaluarsa',
                    'paid_at' => null,
                ]);

                if ($transaksi->pelanggan_id) {
                    $this->notifService->kirim(
                        'inapp', null,
                        'Pembayaran Kadaluarsa',
                        "Pesanan {$merchantOrderId} belum dibayar dan telah kadaluarsa. Silakan buat pesanan baru bila masih ingin membeli.",
                        [
                            'transaksi_id' => $transaksi->id,
                            'pelanggan_id' => $transaksi->pelanggan_id,
                            'kategori' => 'transaksi',
                            'action_url' => '/account',
                            'audience' => 'pelanggan',
                        ]
                    );
                }

                $this->notifService->sinkronkanStatusTransaksi($transaksi, 'kadaluarsa');

                return ['kode' => 'sukses'];
            });
        } catch (\Exception $e) {
            Log::error('[Duitku] Gagal menandai order sebagai kadaluarsa', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['kode' => 'proses_gagal', 'error' => $e->getMessage()];
        }
    }

    /**
     * [B-14] Pisahkan `total_akhir` jadi DPP + PPN untuk jurnal, dengan invarian
     * balance: `total_akhir === dpp + ppn` (dgn presisi 2 desimal).
     *
     * Sumber PPN = kolom transaksi (`ppn_nominal`, diisi `PajakService` saat order
     * dibuat). Fallback `total_akhir - dpp` hanya utk order yang PPN-nya sudah
     * tercakup di total tapi kolom `ppn_nominal`-nya kosong. Order LAMA
     * (`ppn_nominal` kosong & `dpp` = total) → PPN 0, jadi tidak ada PPN
     * retroaktif bila pajak baru diaktifkan (kontrak PajakService: perubahan
     * konfigurasi hanya berlaku utk transaksi baru).
     *
     * @return array{0: float, 1: float} [dpp, ppnNominal]
     */
    private function pisahDppPpn(Transaksi $transaksi, float $total): array
    {
        $ppn = max(0.0, (float) ($transaksi->ppn_nominal ?? 0));

        if ($ppn <= 0) {
            $dppTersimpan = (float) ($transaksi->dpp ?? 0);
            if ($dppTersimpan > 0 && $dppTersimpan < $total) {
                $ppn = round($total - $dppTersimpan, 2);
            }
        }

        $dpp = $ppn > 0 ? round($total - $ppn, 2) : $total;

        return [$dpp, $ppn];
    }
}
