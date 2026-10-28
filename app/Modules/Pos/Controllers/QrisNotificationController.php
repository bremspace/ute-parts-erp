<?php

declare(strict_types=1);

namespace App\Modules\Pos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Pos\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QrisNotificationController extends Controller
{
    /**
     * Handle webhook notifikasi QRIS dari Android forwarder / Tasker / MacroDroid / SMS / m-Banking.
     * Menerima format JSON, x-www-form-urlencoded, GET query parameters, maupun raw text.
     */
    public function handle(Request $request, KonfigurasiService $configService): JsonResponse
    {
        $rawContent = (string) $request->getContent();

        // 0. Auto-parse rawContent jika belum diparse oleh framework (misal Content-Type text/plain atau MacroDroid raw)
        if (filled($rawContent)) {
            $jsonParsed = json_decode($rawContent, true);
            if (is_array($jsonParsed) && json_last_error() === JSON_ERROR_NONE) {
                $request->merge($jsonParsed);
            } elseif (str_contains($rawContent, '=') && (str_contains($rawContent, '&') || str_contains($rawContent, "\n"))) {
                parse_str(str_replace(["\r\n", "\r", "\n"], '&', $rawContent), $parsedRaw);
                if (is_array($parsedRaw) && ! empty($parsedRaw)) {
                    $request->merge($parsedRaw);
                }
            }
        }

        // 1. Ekstrak kandidat secret token dari seluruh sumber (Header, Input, Query, Raw content)
        $candidates = [];

        if (filled($request->header('X-QRIS-Secret'))) {
            $candidates[] = trim((string) $request->header('X-QRIS-Secret'), " \t\n\r\0\x0B\"'");
        }
        if (filled($request->input('secret'))) {
            $candidates[] = trim((string) $request->input('secret'), " \t\n\r\0\x0B\"'");
        }
        if (filled($request->query('secret'))) {
            $candidates[] = trim((string) $request->query('secret'), " \t\n\r\0\x0B\"'");
        }
        if (filled($request->input('token'))) {
            $candidates[] = trim((string) $request->input('token'), " \t\n\r\0\x0B\"'");
        }
        if (filled($request->query('token'))) {
            $candidates[] = trim((string) $request->query('token'), " \t\n\r\0\x0B\"'");
        }

        if (filled($rawContent)) {
            if (preg_match_all('/["\']?(?:secret|token)["\']?\s*[:=]\s*["\']?([^"\'&\s\r\n]+)/i', $rawContent, $mSecs)) {
                foreach ($mSecs[1] as $val) {
                    $candidates[] = trim($val, " \t\n\r\0\x0B\"'");
                }
            }
        }

        $candidates = array_unique(array_filter($candidates, fn ($c) => filled($c)));

        // 2. Validasi secret token jika dikonfigurasi di sistem
        $secretExpected = trim((string) $configService->get('qris_webhook_secret'), " \t\n\r\0\x0B\"'");
        if (filled($secretExpected)) {
            // Lolos jika salah satu kandidat secret token yang dikirim cocok dengan yang diharapkan
            $isAuthorized = in_array($secretExpected, $candidates, true);

            if (! $isAuthorized) {
                $providedList = ! empty($candidates) ? implode(', ', $candidates) : '(kosong)';
                $this->catatLogHistory([
                    'status' => 'unauthorized',
                    'pesan' => 'Secret token tidak cocok (diterima: '.$providedList.', diharapkan: '.$secretExpected.')',
                    'nominal' => null,
                    'title' => (string) ($request->input('title') ?? $request->query('title') ?? 'QRIS Notification'),
                    'raw' => filled($rawContent) ? $rawContent : json_encode($request->all()),
                    'waktu' => now()->format('Y-m-d H:i:s'),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: secret token tidak valid.',
                ], 401);
            }
        }

        // 3. Ekstrak pesan teks dan judul dari berbagai variasi field MacroDroid / PayHook / forwarder Android
        $rawText = (string) (
            $request->input('notification_text')
            ?? $request->input('text')
            ?? $request->input('body')
            ?? $request->input('message')
            ?? $request->input('not_body')
            ?? $request->input('content')
            ?? $request->query('text')
            ?? $request->query('body')
            ?? $request->query('message')
            ?? $rawContent
        );

        $title = (string) (
            $request->input('notification_title')
            ?? $request->input('title')
            ?? $request->query('title')
            ?? $request->input('not_title')
            ?? 'QRIS Notification'
        );

        $nominalInput = $request->input('nominal')
            ?? $request->input('amount')
            ?? $request->query('nominal')
            ?? $request->query('amount');

        // 4. Parse nominal menggunakan parser cerdas (mengakomodasi desimal sen ,00 / .00)
        $nominal = self::extractNominal($nominalInput, $rawText, $rawContent);

        if (empty($nominal) || $nominal <= 0) {
            $this->catatLogHistory([
                'status' => 'gagal',
                'pesan' => 'Nominal tidak terdeteksi dari teks notifikasi',
                'nominal' => null,
                'title' => $title,
                'raw' => $rawText,
                'waktu' => now()->format('Y-m-d H:i:s'),
                'ip' => $request->ip(),
            ]);

            Log::warning('Webhook QRIS diterima tanpa nominal valid', [
                'title' => $title,
                'raw' => $rawText,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendeteksi nominal pembayaran dari teks notifikasi.',
                'raw' => $rawText,
            ], 422);
        }

        // 5. Simpan flag notifikasi pembayaran masuk ke Cache (TTL 5 menit)
        $paymentData = [
            'nominal' => $nominal,
            'title' => $title,
            'text' => $rawText,
            'waktu' => now()->toIso8601String(),
        ];

        Cache::put("qris_payment_{$nominal}", $paymentData, 300);
        Cache::put('qris_latest_payment', $paymentData, 300);

        // Auto-complete transaksi marketplace jika ada order yang sedang menunggu pembayaran dengan nominal pas
        $orderMatched = null;
        try {
            $pendingMp = Transaksi::where('status', 'menunggu_pembayaran')
                ->where('sumber', 'marketplace')
                ->where(function ($q) {
                    $q->where('payment_reference', 'LIKE', 'QRIS-%')
                        ->orWhere('metode_bayar', 'qris');
                })
                ->whereRaw('ROUND(total_akhir) = ?', [$nominal])
                ->latest()
                ->first();

            if ($pendingMp) {
                app(DuitkuPaymentProcessor::class)->prosesLunas(
                    $pendingMp->no_transaksi,
                    $nominal,
                    'QRIS-TOKO',
                    'qris'
                );
                Cache::forget("qris_payment_{$nominal}");
                $orderMatched = $pendingMp->no_transaksi;
                Log::info("Order marketplace {$pendingMp->no_transaksi} otomatis dilunasi via QRIS webhook", [
                    'nominal' => $nominal,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal auto-complete order marketplace via webhook QRIS: '.$e->getMessage());
        }

        $this->catatLogHistory([
            'status' => 'sukses',
            'pesan' => $orderMatched ? "Berhasil melunasi order MP {$orderMatched}" : 'Berhasil diproses ke cache',
            'nominal' => $nominal,
            'title' => $title,
            'raw' => $rawText,
            'waktu' => now()->format('Y-m-d H:i:s'),
            'ip' => $request->ip(),
        ]);

        Log::info("Webhook QRIS berhasil dicatat: Rp {$nominal}", $paymentData);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi QRIS berhasil diproses.',
            'data' => $paymentData,
        ]);
    }

    /**
     * Ekstrak nominal integer dari berbagai format teks perbankan / m-Banking / e-Wallet.
     */
    public static function extractNominal(mixed $nominalInput, string $text, string $rawContent = ''): ?int
    {
        // A. Jika ada parameter nominal langsung dari request
        if (filled($nominalInput)) {
            $nom = self::cleanNominalString((string) $nominalInput);
            if ($nom > 0) {
                return $nom;
            }
        }

        // B. Gabungkan teks pencarian
        $combinedText = $text;
        if (filled($rawContent) && $rawContent !== $text) {
            $combinedText .= ' '.$rawContent;
        }

        if (blank($combinedText)) {
            return null;
        }

        // Pola 1: Dengan kata kunci / mata uang (Rp, IDR, sebesar, masuk, terima, dll)
        $currencyKeywords = '(?:Rp\.?|IDR|sebesar|nominal|total|masuk|terima|jumlah|bayar|nilai)';
        if (preg_match('/'.$currencyKeywords.'\s*[:=]?\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?|[0-9]+(?:[.,][0-9]{2})?)/i', $combinedText, $m)) {
            $nom = self::cleanNominalString($m[1]);
            if ($nom > 0) {
                return $nom;
            }
        }

        // Pola 2: Format mutasi bank CR/DB (contoh SMS/push BCA: "CR 50.000,00" atau "50.000,00 CR")
        if (preg_match('/([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)\s*(?:CR|DB)/i', $combinedText, $m)) {
            $nom = self::cleanNominalString($m[1]);
            if ($nom > 0) {
                return $nom;
            }
        }

        // Pola 3: Pola angka ribuan format Indonesia (contoh: "50.000" atau "50.000,00" atau "1.250.000")
        if (preg_match('/\b([1-9][0-9]{0,2}(?:\.[0-9]{3})+(?:,[0-9]{2})?)\b/', $combinedText, $m)) {
            $nom = self::cleanNominalString($m[1]);
            if ($nom > 0) {
                return $nom;
            }
        }

        // Pola 4: Pola angka ribuan format Standar / US (contoh: "50,000" atau "50,000.00")
        if (preg_match('/\b([1-9][0-9]{0,2}(?:,[0-9]{3})+(?:\.[0-9]{2})?)\b/', $combinedText, $m)) {
            $nom = self::cleanNominalString($m[1]);
            if ($nom > 0) {
                return $nom;
            }
        }

        // Pola 5: Fallback digit polos tanpa titik ribuan (minimal 4 digit: >= 1000)
        if (preg_match('/\b([1-9][0-9]{3,9})\b/', $combinedText, $m)) {
            $nom = (int) $m[1];
            if ($nom >= 1000) {
                return $nom;
            }
        }

        return null;
    }

    /**
     * Bersihkan string nominal: buang desimal sen (,00 / .00), lalu buang karakter non-digit.
     */
    public static function cleanNominalString(string $val): int
    {
        $val = trim($val);

        // Jika memiliki desimal sen 2 digit di akhir (contoh: ",00" atau ".00" atau ",50")
        if (preg_match('/[,.][0-9]{2}$/', $val)) {
            $val = preg_replace('/[,.][0-9]{2}$/', '', $val);
        }

        // Buang semua karakter selain angka
        $digits = preg_replace('/[^0-9]/', '', $val);

        return (int) $digits;
    }

    /**
     * Endpoint polling / pengecekan status pembayaran QRIS untuk nominal tertentu.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $nominal = (int) $request->query('nominal', 0);

        if ($nominal <= 0) {
            return response()->json([
                'paid' => false,
                'message' => 'Nominal tidak valid.',
            ], 400);
        }

        $payment = Cache::get("qris_payment_{$nominal}");

        return response()->json([
            'paid' => ! empty($payment),
            'data' => $payment,
        ]);
    }

    /**
     * Catat riwayat event webhook QRIS ke Cache (maks 15 record terbaru, TTL 7 hari).
     */
    private function catatLogHistory(array $entry): void
    {
        try {
            $history = Cache::get('qris_webhook_log_history', []);
            if (! is_array($history)) {
                $history = [];
            }
            array_unshift($history, $entry);
            $history = array_slice($history, 0, 15);
            Cache::put('qris_webhook_log_history', $history, 86400 * 7);
            Cache::put('qris_last_webhook_received', $entry, 86400 * 7);
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat qris_webhook_log_history: '.$e->getMessage());
        }
    }
}
