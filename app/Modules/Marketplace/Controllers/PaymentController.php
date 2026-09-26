<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Services\StokDeductionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    /**
     * [B-10b/P0-5] Toleransi pencocokan nominal (rupiah bulat).
     * Duitku mengirim amount integer; total_akhir desimal 15,2 → selisih
     * pembulatan 1 rupiah masih dianggap cocok, selebihnya = tidak cocok.
     */
    private const TOLERANSI_AMOUNT = 1;

    /**
     * [B-10b/P0-5] Status final order = callback-nya sudah pernah diproses.
     * Eksplisit (bukan hanya 'lunas') supaya callback gagal/pending yang
     * terlambat tidak menimpa order yang sudah dibayar.
     */
    private const STATUS_FINAL = ['selesai', 'lunas', 'dibatalkan', 'batal', 'kadaluarsa', 'gagal'];

    public function __construct(
        protected DuitkuService $duitku,
        protected JurnalService $jurnalService,
        protected PajakService $pajakService,
        protected StokDeductionService $stokDeduction,
        protected KomisiService $komisiService,
        protected NotificationService $notifService
    ) {}

    /**
     * [API: PAY-01] Buat transaksi pembayaran Duitku utk order menunggu_pembayaran.
     * Body: order_id (no_transaksi) + payment_method (opsional)
     */
    public function create(Request $request)
    {
        $request->validate([
            'no_transaksi' => 'required|string|exists:transaksi,no_transaksi',
            'payment_method' => 'nullable|string',
        ]);

        $transaksi = Transaksi::where('no_transaksi', $request->no_transaksi)
            ->with('pelanggan')
            ->firstOrFail();

        if ($transaksi->status !== 'menunggu_pembayaran') {
            return $this->error('Transaksi tidak dalam status menunggu pembayaran', 422);
        }

        if (! $this->duitku->isConfigured()) {
            return $this->error(
                'Duitku belum dikonfigurasi — set DUITKU_MERCHANT_CODE, DUITKU_API_KEY, DUITKU_MERCHANT_KEY di .env (sandbox=true untuk testing)',
                503
            );
        }

        try {
            $result = $this->duitku->createTransaction(
                $transaksi->no_transaksi,
                (float) $transaksi->total_akhir,
                $transaksi->pelanggan?->nama ?? 'Customer',
                $transaksi->pelanggan?->email,
                $transaksi->pelanggan?->telepon,
                $request->payment_method
            );

            $transaksi->update([
                'payment_reference' => $result['reference'] ?? $result['merchant_order_id'],
                'payment_url' => $result['payment_url'],
            ]);

            return $this->success([
                'no_transaksi' => $transaksi->no_transaksi,
                'payment_url' => $result['payment_url'],
                'reference' => $result['reference'],
                'va_number' => $result['va_number'],
                'qr_string' => $result['qr_string'],
                'expiry' => $result['expiry'],
                'sandbox' => $this->duitku->isSandbox(),
            ], 'Transaksi Duitku berhasil dibuat');
        } catch (\Exception $e) {
            return $this->error('Duitku: '.$e->getMessage(), 502);
        }
    }

    /**
     * [API: PAY-02] Webhook callback Duitku.
     * Wajib verify signature + IDEMPOTEN — callback duplikat tidak boleh kurangi stok 2x / buat jurnal dobel.
     *
     * [B-10b/P0-5] Race-safe: SELURUH proses (cek status → validasi nominal →
     * potong stok → jurnal → komisi → notifikasi) berada dalam SATU
     * DB::transaction dgn `lockForUpdate()` pada baris `transaksi`. Dua webhook
     * bersamaan tidak bisa dua-duanya lolos guard status → hanya satu yang
     * memproses, yang kedua dapat "Already processed" tanpa efek samping.
     */
    public function webhook(Request $request)
    {
        $merchantCode = $request->input('merchantCode');
        $amount = (int) $request->input('amount');
        $merchantOrderId = (string) $request->input('merchantOrderId');
        $resultCode = (string) $request->input('resultCode'); // 00 = sukses
        $signature = (string) $request->input('signature');

        Log::info('[Duitku-webhook] Incoming', $request->all());

        // 1. Verify signature (anti-perusakan)
        if (! $this->duitku->verifyCallbackSignature($amount, $merchantOrderId, $signature)) {
            Log::warning('[Duitku-webhook] Signature mismatch', ['order' => $merchantOrderId]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        // 2. Verify merchant code
        if ($merchantCode !== config('duitku.merchant_code')) {
            return response()->json(['success' => false, 'message' => 'Invalid merchant'], 400);
        }

        // 3. Eksistensi order (cek ringan, tanpa lock — lock diambil di dalam
        //    transaksi pemrosesan supaya tidak menahan lock terlalu lama).
        if (! Transaksi::where('no_transaksi', $merchantOrderId)->exists()) {
            Log::warning('[Duitku-webhook] Order tidak ditemukan', ['order' => $merchantOrderId]);

            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // ===== PROCESS =====
        if ($resultCode === '00') {
            return $this->prosesLunas($merchantOrderId, $amount);
        }

        if (in_array($resultCode, ['01', '02', '03', '04', '05'], true)) {
            return $this->prosesGagal($merchantOrderId, $resultCode);
        }

        // Status lain — tandai pending saja, bukan final
        return response()->json(['success' => true, 'message' => 'Received']);
    }

    /**
     * [B-10b/P0-5] Callback sukses (resultCode 00): order → lunas dalam satu
     * transaksi atomik + row lock. Idempoten: order yang statusnya sudah final
     * dikembalikan sukses TANPA potong stok / jurnal / notifikasi lagi.
     */
    private function prosesLunas(string $merchantOrderId, int $amount): JsonResponse
    {
        try {
            $hasil = DB::transaction(function () use ($merchantOrderId, $amount) {
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

                // a. Update status transaksi SEBELUM operasi lain (idempotency marker)
                $transaksi->update([
                    'status' => 'lunas',
                    'metode_bayar' => 'duitku',
                    'jumlah_bayar' => (float) $transaksi->total_akhir,
                    'paid_at' => now(),
                ]);

                // b. Kurangi stok per item (gudang tersedia di cabang transaksi)
                $cabangId = $transaksi->cabang_id ?: Cabang::firstOrFail()->id;
                foreach ($transaksi->items as $item) {
                    $this->stokDeduction->kurangiDariGudangTersedia(
                        $item->produk_id,
                        $item->sku_variant_id,
                        (int) $item->jumlah,
                        $cabangId,
                        'penjualan',
                        Transaksi::class,
                        $transaksi->id,
                        null,
                        "POS Online {$merchantOrderId} (lunas Duitku)"
                    );
                }

                // c. Jurnal otomatis: Kas masuk / Pendapatan / PPN / HPP / Persediaan turun
                //    [B-14] PPN dipisah ke akun pajak 220-01 — POLA SAMA dgn POS
                //    (`PosController::store()`): debit Kas = total_akhir (nilai yang
                //    benar-benar dibayar pelanggan, TIDAK diubah), kredit pendapatan
                //    410-01 = DPP, kredit 220-01 = PPN. Tanpa ini seluruh
                //    `total_akhir` masuk pendapatan → laporan penjualan marketplace
                //    ≠ POS + laporan pajak F1-2 under-reported.
                //    PPN TIDAK dihitung ulang di sini: nominalnya sudah disimpan
                //    saat order dibuat (`OrderService::buatOrder` → PajakService)
                //    sehingga tetap non-retroaktif bila konfigurasi pajak berubah.
                $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
                $totalAkhir = (float) $transaksi->total_akhir;
                [$dpp, $ppnNominal] = $this->pisahDppPpn($transaksi, $totalAkhir);
                $noJurnal = $this->jurnalService->generateNoJurnal('pos', $cabangId);

                $lines = [
                    ['akun_kode' => '110-01', 'debit' => $totalAkhir, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $dpp],
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
                    "Jurnal order online {$merchantOrderId} (lunas Duitku)",
                    $cabangId,
                    null,
                    Transaksi::class,
                    $transaksi->id
                );

                // d. Komisi multi-aktor (PRD §4.3): reseller/agen/karyawan marketing
                $this->komisiService->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

                // e. Notifikasi pelanggan (via queue)
                $this->notifService->kirim(
                    'inapp', null,
                    'Pembayaran Diterima',
                    "Pesanan {$merchantOrderId} lunas — stok siap diproses. Terima kasih!",
                    ['transaksi_id' => $transaksi->id]
                );

                return ['kode' => 'sukses'];
            });
        } catch (\Exception $e) {
            // Rollback DB::transaction sudah mengembalikan status/stok/jurnal.
            // TIDAK perlu update status manual lagi (bisa menimpa order yang
            // bersamaan sudah 'lunas').
            Log::error('[Duitku-webhook] Process failed, transaction rolled back', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
        }

        return $this->responsHasilLunas($merchantOrderId, $hasil);
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

    /** Mapping hasil prosesLunas() → respons JSON. */
    private function responsHasilLunas(string $merchantOrderId, array $hasil): JsonResponse
    {
        if ($hasil['kode'] === 'sukses') {
            Log::info('[Duitku-webhook] Payment success processed', ['order' => $merchantOrderId]);

            return response()->json(['success' => true, 'message' => 'OK']);
        }

        if ($hasil['kode'] === 'sudah_diproses') {
            Log::info('[Duitku-webhook] Idempotent skip — status sudah final', [
                'order' => $merchantOrderId,
                'status' => $hasil['status'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        if ($hasil['kode'] === 'nominal_tidak_cocok') {
            // HTTP 200 + success=false: selisih nominal tidak akan membaik dengan
            // retry, jadi callback dihentikan (tidak ada badai retry). Dana sudah
            // masuk di Duitku → wajib rekonsiliasi manual (Log::error).
            Log::error('[Duitku-webhook] Nominal tidak cocok — tidak diproses', [
                'order' => $merchantOrderId,
                'expected' => $hasil['expected'],
                'diterima' => $hasil['diterima'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nominal pembayaran tidak sesuai dengan total transaksi (tagihan Rp '
                    .number_format($hasil['expected'], 0, ',', '.')
                    .', diterima Rp '.number_format($hasil['diterima'], 0, ',', '.')
                    .') — pembayaran tidak diproses, perlu rekonsiliasi manual',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Order not found'], 404);
    }

    /**
     * [B-10b/P0-5] Callback gagal/kadaluarsa (resultCode 01-05) — juga di row
     * lock supaya tidak menimpa order yang bersamaan sudah 'lunas'.
     */
    private function prosesGagal(string $merchantOrderId, string $resultCode): JsonResponse
    {
        try {
            $hasil = DB::transaction(function () use ($merchantOrderId) {
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

                $this->notifService->kirim(
                    'inapp', null,
                    'Pembayaran Gagal',
                    "Pesanan {$merchantOrderId} gagal dibayar. Silakan coba kembali.",
                    ['transaksi_id' => $transaksi->id]
                );

                return ['kode' => 'sukses'];
            });
        } catch (\Exception $e) {
            Log::error('[Duitku-webhook] Gagal menandai order sebagai gagal', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
        }

        if ($hasil['kode'] === 'tidak_ditemukan') {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($hasil['kode'] === 'sudah_diproses') {
            Log::info('[Duitku-webhook] Idempotent skip — status sudah final', [
                'order' => $merchantOrderId,
                'status' => $hasil['status'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        Log::info('[Duitku-webhook] Payment failed', ['order' => $merchantOrderId, 'resultCode' => $resultCode]);

        return response()->json(['success' => true, 'message' => 'Recorded as failed']);
    }

    /**
     * Daftar metode pembayaran Duitku (dinamis).
     */
    public function paymentMethods(Request $request)
    {
        $amount = (float) ($request->query('amount', 0));
        try {
            $methods = $this->duitku->getPaymentMethods($amount);

            return $this->success($methods, 'Daftar metode pembayaran berhasil dimuat');
        } catch (\Exception $e) {
            return $this->error('Duitku: '.$e->getMessage(), 502);
        }
    }
}
