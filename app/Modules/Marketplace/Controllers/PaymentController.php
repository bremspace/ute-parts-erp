<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Pos\Models\Transaksi;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DuitkuService $duitku,
        protected DuitkuPaymentProcessor $processor
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
     * DB::transaction dgn `lockForUpdate()` pada baris `transaksi` (kini di
     * `DuitkuPaymentProcessor::prosesLunas` — dipakai bersama dgn `duitku:rekon`).
     * Dua webhook bersamaan tidak bisa dua-duanya lolos guard status → hanya
     * satu yang memproses, yang kedua dapat "Already processed" tanpa efek samping.
     */
    public function webhook(Request $request)
    {
        $merchantCode = $request->input('merchantCode');
        $amount = (int) $request->input('amount');
        $merchantOrderId = (string) $request->input('merchantOrderId');
        $resultCode = (string) $request->input('resultCode'); // 00 = sukses
        $signature = (string) $request->input('signature');
        $reference = $request->input('reference');

        // Sanitasi log: jangan log signature/payload penuh (bisa berisi data pelanggan)
        Log::info('[Duitku-webhook] Incoming', [
            'merchantOrderId' => $merchantOrderId,
            'resultCode' => $resultCode,
            'amount' => $amount,
        ]);

        // 1. Verify signature (anti-perusakan) — dual HMAC-SHA256 (docs baru) + MD5 (legacy)
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
            $hasil = $this->processor->prosesLunas($merchantOrderId, $amount, is_string($reference) ? $reference : null);

            return $this->responsHasilLunas($merchantOrderId, $hasil);
        }

        if (in_array($resultCode, ['01', '02', '03', '04', '05'], true)) {
            $hasil = $this->processor->prosesGagal($merchantOrderId, $resultCode);

            return $this->responsHasilGagal($merchantOrderId, $resultCode, $hasil);
        }

        // Status lain — tandai pending saja, bukan final
        return response()->json(['success' => true, 'message' => 'Received']);
    }

    /** Mapping hasil DuitkuPaymentProcessor::prosesLunas() → respons JSON. */
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

        if ($hasil['kode'] === 'proses_gagal') {
            // Rollback sudah terjadi di processor — respons 500 agar Duitku retry.
            return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
        }

        return response()->json(['success' => false, 'message' => 'Order not found'], 404);
    }

    /** Mapping hasil DuitkuPaymentProcessor::prosesGagal() → respons JSON. */
    private function responsHasilGagal(string $merchantOrderId, string $resultCode, array $hasil): JsonResponse
    {
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

        if ($hasil['kode'] === 'proses_gagal') {
            return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
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

    /**
     * Cek status pembayaran ke Duitku secara on-demand & sinkronisasi otomatis.
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'no_transaksi' => 'required|string|exists:transaksi,no_transaksi',
        ]);

        $transaksi = Transaksi::where('no_transaksi', $request->no_transaksi)->firstOrFail();

        // Jika sudah final, langsung kembalikan status saat ini
        if ($transaksi->status !== 'menunggu_pembayaran') {
            return $this->success([
                'no_transaksi' => $transaksi->no_transaksi,
                'status' => $transaksi->status,
                'is_lunas' => in_array($transaksi->status, ['lunas', 'diproses', 'dikirim', 'siap_diambil', 'selesai'], true),
            ], 'Status terkini transaksi');
        }

        // Tanyakan status live ke server Duitku
        $res = $this->duitku->transactionStatus($transaksi->no_transaksi);

        if ($res['status'] === 'sukses' && $res['status_code'] === '00') {
            $amount = $res['amount'] ?? (int) round($transaksi->total_akhir);
            $this->processor->prosesLunas($transaksi->no_transaksi, $amount, $res['reference']);
            $transaksi->refresh();

            return $this->success([
                'no_transaksi' => $transaksi->no_transaksi,
                'status' => $transaksi->status,
                'is_lunas' => true,
            ], 'Pembayaran berhasil diverifikasi lunas');
        }

        if ($res['status'] === 'batal' || in_array($res['status_code'], ['02', '03'], true)) {
            $this->processor->prosesGagal($transaksi->no_transaksi, (string) $res['status_code']);
            $transaksi->refresh();

            return $this->success([
                'no_transaksi' => $transaksi->no_transaksi,
                'status' => $transaksi->status,
                'is_lunas' => false,
            ], 'Transaksi telah dibatalkan atau kadaluarsa');
        }

        return $this->success([
            'no_transaksi' => $transaksi->no_transaksi,
            'status' => $transaksi->status,
            'is_lunas' => false,
            'duitku_status' => $res['status'],
        ], 'Transaksi masih menunggu pembayaran');
    }
}
