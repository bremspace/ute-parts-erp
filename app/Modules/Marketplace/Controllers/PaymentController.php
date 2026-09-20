<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Services\StokDeductionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DuitkuService $duitku,
        protected JurnalService $jurnalService,
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
     */
    public function webhook(Request $request)
    {
        $merchantCode = $request->input('merchantCode');
        $amount = (int) $request->input('amount');
        $merchantOrderId = (string) $request->input('merchantOrderId');
        $resultCode = (string) $request->input('resultCode'); // 00 = sukses
        $reference = $request->input('reference');
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

        // 3. Cari transaksi
        $transaksi = Transaksi::with(['items.produk', 'pelanggan.tierMembership'])
            ->where('no_transaksi', $merchantOrderId)
            ->first();

        if (! $transaksi) {
            Log::warning('[Duitku-webhook] Order tidak ditemukan', ['order' => $merchantOrderId]);

            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // ===== IDEMPOTENCY GUARD =====
        // Transaksi selesai/lunas/batal = callback sudah diproses sebelumnya — jangan proses ulang.
        if (in_array($transaksi->status, ['selesai', 'lunas', 'dibatalkan', 'batal', 'kadaluarsa', 'gagal'], true)) {
            Log::info('[Duitku-webhook] Idempotent skip — status sudah final', [
                'order' => $merchantOrderId,
                'status' => $transaksi->status,
            ]);

            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // ===== PROCESS =====
        if ($resultCode === '00') {
            // BAYAR LUNAS → kurangi stok + jurnal + komisi + notifikasi (dalam 1 transaksi atomik)
            try {
                DB::transaction(function () use ($transaksi, $merchantOrderId) {
                    // a. Update status transaksi SEBELUM operasi lain (idempotency lock)
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

                    // c. Jurnal otomatis: Kas masuk / Pendapatan / HPP / Persediaan turun
                    $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
                    $lines = [
                        ['akun_kode' => '110-01', 'debit' => (float) $transaksi->total_akhir, 'kredit' => 0],
                        ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => (float) $transaksi->total_akhir],
                    ];
                    if ($totalHpp > 0) {
                        $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                        $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
                    }
                    $this->jurnalService->post(
                        $this->jurnalService->generateNoJurnal('pos', $cabangId),
                        now(),
                        'pos',
                        $lines,
                        "Jurnal order online {$merchantOrderId} (lunas Duitku)",
                        $cabangId,
                        null,
                        Transaksi::class,
                        $transaksi->id
                    );

                    // d. Komisi reseller (jika pembeli reseller)
                    if ($transaksi->pelanggan?->is_reseller) {
                        $this->komisiService->hitungKomisi($transaksi, $transaksi->pelanggan);
                    }

                    // e. Notifikasi pelanggan (via queue)
                    $this->notifService->kirim(
                        'inapp', null,
                        'Pembayaran Diterima',
                        "Pesanan {$merchantOrderId} lunas — stok siap diproses. Terima kasih!",
                        ['transaksi_id' => $transaksi->id]
                    );
                });

                Log::info('[Duitku-webhook] Payment success processed', ['order' => $merchantOrderId]);

                return response()->json(['success' => true, 'message' => 'OK']);
            } catch (\Exception $e) {
                Log::error('[Duitku-webhook] Process failed, status revoked', [
                    'order' => $merchantOrderId,
                    'error' => $e->getMessage(),
                ]);
                // Kembalikan status agar callback retry Duitku bisa coba lagi (dengan retro check)
                $transaksi->update([
                    'status' => 'menunggu_pembayaran',
                    'paid_at' => null,
                ]);

                // Partial stok deduction di rollback oleh DB::transaction — aman.
                return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
            }
        }

        if (in_array($resultCode, ['01', '02', '03', '04', '05'], true)) {
            // Gagal / kadaluarsa / dibatalkan (kode non-00)
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

            Log::info('[Duitku-webhook] Payment failed', ['order' => $merchantOrderId, 'resultCode' => $resultCode]);

            return response()->json(['success' => true, 'message' => 'Recorded as failed']);
        }

        // Status lain — tandai pending saja, bukan final
        return response()->json(['success' => true, 'message' => 'Received']);
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
