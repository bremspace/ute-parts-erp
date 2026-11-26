<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Duitku Payment Gateway (PRD §4.7) — docs.duitku.com (last-modified 30 Sep 2026).
 *
 * Endpoint (base: sandbox https://sandbox.duitku.com/webapi/api/merchant,
 * produksi https://passport.duitku.com/webapi/api/merchant):
 *  - POST /v2/inquiry                    — buat invoice (createTransaction)
 *  - POST /paymentmethod/getpaymentmethod — daftar metode (getPaymentMethods)
 *  - POST /transactionStatus             — cek status (transactionStatus)
 *
 * Signature HMAC-SHA256 (docs resmi):
 *  - inquiry : hash_hmac('sha256', merchantCode.merchantOrderId.paymentAmount, apiKey)
 *  - methods : hash_hmac('sha256', merchantCode.paymentAmount.datetime, apiKey) — datetime = date('Y-m-d H:i:s')
 *  - status  : hash_hmac('sha256', merchantCode.merchantOrderId, apiKey)
 *  - callback: hash_hmac('sha256', merchantCode.amount.merchantOrderId, apiKey)
 *
 * Transisi signature (config duitku.signature_mode, env DUITKU_SIGNATURE_MODE):
 *  - 'hmac' — outbound & verifikasi HMAC saja (tujuan akhir)
 *  - 'md5'  — outbound & verifikasi MD5 lama saja (rollback darurat)
 *  - 'dual' (default) — outbound HMAC; verifikasi callback menerima HMAC ATAU MD5
 *
 * Sandbox default true sampai lolos UAT (DUITKU_SANDBOX=true).
 */
class DuitkuService
{
    private string $merchantCode;

    private string $apiKey;

    private string $merchantKey;

    private bool $sandbox;

    private string $baseUrl;

    /** hmac | md5 | dual — default dual selama masa transisi. */
    private string $signatureMode;

    private int $expiryPeriod;

    public function __construct()
    {
        $cfg = app(KonfigurasiService::class)->get('duitku_gateway_config');
        if (is_array($cfg) && filled($cfg['merchant_code'] ?? null)) {
            $this->merchantCode = trim((string) ($cfg['merchant_code'] ?? ''));
            $this->apiKey = trim((string) ($cfg['api_key'] ?? ''));
            $this->merchantKey = trim((string) ($cfg['merchant_key'] ?? ''));
            $this->sandbox = ($cfg['mode'] ?? 'sandbox') === 'sandbox';
            $this->expiryPeriod = max(15, (int) ($cfg['expiry_period'] ?? config('duitku.expiry_period', 1440)));
        } else {
            $this->merchantCode = trim((string) config('duitku.merchant_code'));
            $this->apiKey = trim((string) config('duitku.api_key'));
            $this->merchantKey = trim((string) config('duitku.merchant_key'));
            $this->sandbox = (bool) config('duitku.sandbox');
            $this->expiryPeriod = max(15, (int) config('duitku.expiry_period', 1440));
        }

        // Di portal Duitku hanya ada 1 project API key; bila admin hanya mengisi salah satu field, fallback otomatis.
        if ($this->apiKey === '' && $this->merchantKey !== '') {
            $this->apiKey = $this->merchantKey;
        } elseif ($this->merchantKey === '' && $this->apiKey !== '') {
            $this->merchantKey = $this->apiKey;
        }

        $this->baseUrl = $this->sandbox
            ? config('duitku.sandbox_url')
            : config('duitku.production_url');

        $mode = strtolower((string) config('duitku.signature_mode', 'dual'));
        $this->signatureMode = in_array($mode, ['hmac', 'md5', 'dual'], true) ? $mode : 'dual';
    }

    public function isConfigured(): bool
    {
        return $this->merchantCode !== '' && ($this->apiKey !== '' || $this->merchantKey !== '');
    }

    public function getMerchantCode(): string
    {
        return $this->merchantCode;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getMerchantKey(): string
    {
        return $this->merchantKey;
    }

    /**
     * Buat invoice Duitku via POST /v2/inquiry.
     *
     * merchantOrderId adalah idempotency-key: retry aman (Duitku menolak duplikat
     * dengan pesan "already exists"), jadi cukup 1 retry.
     *
     * @param  string  $merchantOrderId  no_transaksi lokal (≤50 char, unik)
     * @param  string|null  $paymentMethod  kode 2 huruf: VA|QRIS|OVO|... (null → default 'VA')
     * @return array{merchant_order_id: ?string, reference: ?string, payment_url: ?string, va_number: ?string, qr_string: ?string, expiry: ?string, app_url: ?string}
     */
    public function createTransaction(
        string $merchantOrderId,
        float $amount,
        string $customerName,
        ?string $customerEmail = null,
        ?string $phoneNumber = null,
        ?string $paymentMethod = null,
        ?string $callbackUrl = null,
        ?string $returnUrl = null
    ): array {
        $paymentAmount = (int) round($amount);

        $targetCallbackUrl = $callbackUrl ?: config('duitku.callback_url');
        if (! $targetCallbackUrl) {
            $targetCallbackUrl = url('/webhook/duitku');
        }
        // Duitku tolak port non-standar (seperti :8080) — normalisasi ke port standar
        $parsedCb = parse_url($targetCallbackUrl);
        if (isset($parsedCb['port']) && ! in_array((int) $parsedCb['port'], [80, 443], true)) {
            $targetCallbackUrl = ($parsedCb['scheme'] ?? 'http').'://'.$parsedCb['host'].($parsedCb['path'] ?? '/webhook/duitku');
        }

        $basePayload = array_filter([
            'merchantCode' => $this->merchantCode,
            'paymentAmount' => $paymentAmount,
            'paymentMethod' => $paymentMethod ?: 'VA',
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => mb_substr('Order '.config('app.name', 'Bremspace').' '.$merchantOrderId, 0, 255),
            // email field wajib di docs — placeholder bila pelanggan tanpa email
            'email' => $customerEmail ?: 'customer@bremspace.id',
            'customerVaName' => mb_substr($customerName !== '' ? $customerName : 'Customer', 0, 20),
            'phoneNumber' => $phoneNumber ?: '081234567890',
            'callbackUrl' => $targetCallbackUrl,
            // Setelah bayar di halaman Duitku, kembali ke Akun Saya (daftar
            // pesanan + status live) — bukan checkout kosong.
            'returnUrl' => $returnUrl ?: url('/account'),
            'expiryPeriod' => $this->expiryPeriod,
        ], fn ($v) => $v !== null);

        $response = $this->postWithSignatureFallback(
            '/v2/inquiry',
            $basePayload,
            $this->merchantCode.$merchantOrderId.$paymentAmount
        );

        $data = $response->json() ?? [];

        if (! $this->isSuccessResponse($data)) {
            $pesan = $this->pickField($data, 'statusMessage')
                ?: $this->pickField($data, 'message')
                ?: 'Transaksi gagal dibuat (kode: '.($this->pickField($data, 'statusCode') ?: $this->pickField($data, 'responseCode') ?: 'tanpa kode').')';

            throw new \Exception((string) $pesan);
        }

        return [
            'merchant_order_id' => $this->pickField($data, 'merchantOrderId') ?? $merchantOrderId,
            'reference' => $this->pickField($data, 'reference') ?? null,
            'payment_url' => $this->pickField($data, 'paymentUrl') ?? null,
            'va_number' => $this->pickField($data, 'vaNumber') ?? null,
            'qr_string' => $this->pickField($data, 'qrString') ?? null,
            'expiry' => $this->pickField($data, 'expiryTime') ?? null,
            'app_url' => $this->pickField($data, 'appUrl') ?? null,
        ];
    }

    /**
     * Cek status transaksi via POST /transactionStatus.
     *
     * @return array{status: string, status_code: ?string, reference: ?string, amount: ?int, fee: ?int}
     *                                                                                                  status: 'sukses' (00) | 'pending' (01) | 'batal' (02) | 'unknown' | 'error'
     */
    public function transactionStatus(string $merchantOrderId): array
    {
        $payload = [
            'merchantCode' => $this->merchantCode,
            'merchantOrderId' => $merchantOrderId,
            'signature' => $this->sign($this->merchantCode.$merchantOrderId),
        ];

        try {
            $response = Http::timeout(20)->retry(2, 1000)->post($this->baseUrl.'/transactionStatus', $payload);
        } catch (\Exception $e) {
            Log::warning('[Duitku] transactionStatus gagal dijangkau', [
                'order' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'status_code' => null, 'reference' => null, 'amount' => null, 'fee' => null];
        }

        $data = $response->json() ?? [];
        $code = $this->pickField($data, 'statusCode') ?? $this->pickField($data, 'responseCode');
        $code = $code !== null ? (string) $code : null;

        $status = match ($code) {
            '00' => 'sukses',
            '01' => 'pending',
            '02' => 'batal',
            default => 'unknown',
        };

        return [
            'status' => $status,
            'status_code' => $code,
            'reference' => $this->pickField($data, 'reference') ?? null,
            'amount' => $this->pickField($data, 'amount') !== null ? (int) $this->pickField($data, 'amount') : null,
            'fee' => $this->pickField($data, 'fee') !== null ? (int) $this->pickField($data, 'fee') : null,
        ];
    }

    /**
     * Daftar metode pembayaran aktif dari Duitku (POST /paymentmethod/getpaymentmethod).
     * Ambil dinamis, jangan hardcode. Signature = merchantCode.amount.datetime
     * (field payload = 'amount' — diverifikasi live ke sandbox 2026-10-17).
     *
     * @return array<int, array{kode: ?string, nama: ?string, fee: mixed}>
     */
    public function getPaymentMethods(float $amount): array
    {
        $paymentAmount = (int) round($amount);
        $datetime = date('Y-m-d H:i:s');

        $basePayload = [
            'merchantCode' => $this->merchantCode,
            // Uji live sandbox (2026-10-17): field bernama 'amount' — docs
            // menulis 'paymentAmount' tapi server hanya membaca 'amount'
            // (signature cocok hanya saat field ini ada).
            'amount' => $paymentAmount,
            'datetime' => $datetime,
        ];

        $response = $this->postWithSignatureFallback(
            '/paymentmethod/getpaymentmethod',
            $basePayload,
            $this->merchantCode.$paymentAmount.$datetime
        );

        $data = $response->json() ?? [];

        if (! $this->isSuccessResponse($data)) {
            $pesan = $this->pickField($data, 'message') ?: 'Gagal mengambil daftar metode pembayaran';

            throw new \Exception((string) $pesan);
        }

        $methods = collect($this->pickField($data, 'paymentFee') ?? []);

        return $methods
            ->filter(function ($m) {
                $kode = strtoupper((string) ($m['paymentMethod'] ?? ''));

                // Filter out paylater bermasalah di sandbox atau yang butuh data kredit khusus (seperti Indodana DN)
                return ! in_array($kode, ['DN', 'AT'], true);
            })
            ->map(function ($m) {
                $kode = (string) ($m['paymentMethod'] ?? '');
                $rawName = (string) ($m['paymentName'] ?? $m['paymentMethodName'] ?? '');
                $meta = $this->metaMetodePembayaran($kode, $rawName);

                return [
                    'kode' => $kode,
                    'nama' => $meta['nama'],
                    'fee' => $m['totalFee'] ?? 0,
                    'kategori' => $meta['kategori'],
                    'badge' => $meta['badge'],
                    'deskripsi' => $meta['deskripsi'],
                    '_urutan' => $meta['urutan'],
                ];
            })
            ->sortBy('_urutan')
            ->values()
            ->map(function ($item) {
                unset($item['_urutan']);

                return $item;
            })
            ->toArray();
    }

    /**
     * Kategorisasi, normalisasi nama, dan badge ringkas per metode Duitku.
     *
     * @return array{kategori: string, badge: string, nama: string, deskripsi: string, urutan: int}
     */
    private function metaMetodePembayaran(string $kode, string $rawName): array
    {
        $k = strtoupper(trim($kode));

        return match ($k) {
            'SP', 'NQ', 'SQ', 'TQ' => [
                'kategori' => 'qris',
                'badge' => 'QRIS',
                'nama' => 'QRIS (Universal Semua Bank & E-Wallet)',
                'deskripsi' => 'Scan via BCA, Mandiri, BRI, BNI, DANA, GoPay, OVO, ShopeePay',
                'urutan' => 1,
            ],
            'BC' => [
                'kategori' => 'va',
                'badge' => 'BCA',
                'nama' => 'BCA Virtual Account',
                'deskripsi' => 'myBCA, BCA mobile, KlikBCA, ATM BCA',
                'urutan' => 2,
            ],
            'M2' => [
                'kategori' => 'va',
                'badge' => 'Mandiri',
                'nama' => 'Mandiri Virtual Account',
                'deskripsi' => 'Livin\' by Mandiri, ATM Mandiri',
                'urutan' => 3,
            ],
            'BR' => [
                'kategori' => 'va',
                'badge' => 'BRI',
                'nama' => 'BRI Virtual Account (BRIVA)',
                'deskripsi' => 'BRImo, ATM BRI, Agen BRILink',
                'urutan' => 4,
            ],
            'I1' => [
                'kategori' => 'va',
                'badge' => 'BNI',
                'nama' => 'BNI Virtual Account',
                'deskripsi' => 'BNI Mobile Banking, ATM BNI',
                'urutan' => 5,
            ],
            'B1' => [
                'kategori' => 'va',
                'badge' => 'CIMB',
                'nama' => 'CIMB Niaga Virtual Account',
                'deskripsi' => 'OCTO Mobile, OCTO Clicks, ATM CIMB',
                'urutan' => 6,
            ],
            'BT' => [
                'kategori' => 'va',
                'badge' => 'Permata',
                'nama' => 'Permata Virtual Account',
                'deskripsi' => 'PermataME, ATM Permata',
                'urutan' => 7,
            ],
            'BV' => [
                'kategori' => 'va',
                'badge' => 'BSI',
                'nama' => 'BSI Virtual Account',
                'deskripsi' => 'BSI Mobile, ATM BSI',
                'urutan' => 8,
            ],
            'NC' => [
                'kategori' => 'va',
                'badge' => 'BNC',
                'nama' => 'Bank Neo Commerce VA',
                'deskripsi' => 'Aplikasi neobank, ATM Bersama',
                'urutan' => 9,
            ],
            'VA' => [
                'kategori' => 'va',
                'badge' => 'Maybank',
                'nama' => 'Maybank Virtual Account',
                'deskripsi' => 'M2U ID App, ATM Maybank',
                'urutan' => 10,
            ],
            'A1' => [
                'kategori' => 'va',
                'badge' => 'ATM Bersama',
                'nama' => 'ATM Bersama / Prima / Alto',
                'deskripsi' => 'Transfer antar-bank ke nomor virtual account',
                'urutan' => 11,
            ],
            'AG' => [
                'kategori' => 'va',
                'badge' => 'Artha Graha',
                'nama' => 'Bank Artha Graha VA',
                'deskripsi' => 'AG Mobile, ATM Artha Graha',
                'urutan' => 12,
            ],
            'S1' => [
                'kategori' => 'va',
                'badge' => 'Sampoerna',
                'nama' => 'Bank Sahabat Sampoerna VA',
                'deskripsi' => 'Transfer virtual account',
                'urutan' => 13,
            ],
            'DA' => [
                'kategori' => 'ewallet',
                'badge' => 'DANA',
                'nama' => 'DANA (Dompet Digital)',
                'deskripsi' => 'Buka aplikasi DANA atau gunakan QRIS untuk bayar instan',
                'urutan' => 20,
            ],
            'OV' => [
                'kategori' => 'ewallet',
                'badge' => 'OVO',
                'nama' => 'OVO',
                'deskripsi' => 'Aplikasi OVO',
                'urutan' => 21,
            ],
            'SA' => [
                'kategori' => 'ewallet',
                'badge' => 'ShopeePay',
                'nama' => 'ShopeePay App',
                'deskripsi' => 'Aplikasi Shopee',
                'urutan' => 22,
            ],
            'LA' => [
                'kategori' => 'ewallet',
                'badge' => 'LinkAja',
                'nama' => 'LinkAja',
                'deskripsi' => 'Aplikasi LinkAja',
                'urutan' => 23,
            ],
            'IR' => [
                'kategori' => 'retail',
                'badge' => 'Indomaret',
                'nama' => 'Indomaret / Ceriamart',
                'deskripsi' => 'Tunjukkan kode bayar ke kasir Indomaret',
                'urutan' => 30,
            ],
            'FT' => [
                'kategori' => 'retail',
                'badge' => 'Retail',
                'nama' => 'Alfamart / Pegadaian / Pos',
                'deskripsi' => 'Tunjukkan kode bayar ke kasir Alfamart / Pos / Pegadaian',
                'urutan' => 31,
            ],
            'VC' => [
                'kategori' => 'kartu',
                'badge' => 'Kartu Kredit',
                'nama' => 'Kartu Kredit / Debit Online',
                'deskripsi' => 'Visa, Mastercard, JCB (3D Secure)',
                'urutan' => 40,
            ],
            'JP' => [
                'kategori' => 'kartu',
                'badge' => 'Jenius',
                'nama' => 'Jenius Pay',
                'deskripsi' => 'Autentikasi via $Cashtag Jenius',
                'urutan' => 41,
            ],
            default => [
                'kategori' => 'va',
                'badge' => strtoupper($kode),
                'nama' => $rawName ?: $kode,
                'deskripsi' => 'Metode pembayaran terintegrasi',
                'urutan' => 50,
            ],
        };
    }

    /**
     * Verify signature callback webhook Duitku (PAY-02).
     *
     * Docs resmi: hash_hmac('sha256', merchantCode.amount.merchantOrderId, apiKey).
     * Legacy (MD5): md5(merchantCode + amount + merchantOrderId + apiKey).
     * Mode dual menerima keduanya (hash_equals, timing-safe) selama transisi.
     */
    public function verifyCallbackSignature(int $amount, string $merchantOrderId, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        if (in_array($this->signatureMode, ['hmac', 'dual'], true)) {
            $expected = hash_hmac('sha256', $this->merchantCode.$amount.$merchantOrderId, $this->apiKey);
            if (hash_equals($expected, $signature)) {
                return true;
            }
            if ($this->signatureMode === 'hmac') {
                return false;
            }
        }

        // fallback MD5 (legacy) — verifikasi terhadap apiKey atau merchantKey
        $expectedMd5Api = md5($this->merchantCode.$amount.$merchantOrderId.$this->apiKey);
        if (hash_equals($expectedMd5Api, $signature)) {
            return true;
        }

        if ($this->merchantKey !== '') {
            $expectedMd5Merchant = md5($this->merchantCode.$amount.$merchantOrderId.$this->merchantKey);
            if (hash_equals($expectedMd5Merchant, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tanda tangan outbound sesuai signature_mode.
     * hmac/dual → HMAC-SHA256 (docs resmi); md5 → md5(stringToSign + apiKey) (legacy).
     */
    private function sign(string $stringToSign, ?string $keyOverride = null): string
    {
        $key = $keyOverride ?? $this->apiKey;

        if ($this->signatureMode === 'md5') {
            return md5($stringToSign.$key);
        }

        return hash_hmac('sha256', $stringToSign, $key);
    }

    /**
     * Kirim POST ke endpoint Duitku dengan fallback otomatis ke merchantKey bila
     * apiKey dan merchantKey berbeda serta respon mengembalikan 403 Invalid Signature.
     */
    private function postWithSignatureFallback(string $path, array $basePayload, string $stringToSign): Response
    {
        $url = $this->baseUrl.$path;
        $payload = array_merge($basePayload, ['signature' => $this->sign($stringToSign, $this->apiKey)]);
        $shouldRetry = fn (\Throwable $e): bool => ! ($e instanceof RequestException && $e->response->status() === 403);

        try {
            return Http::timeout(20)->retry(2, 1000, $shouldRetry)->post($url, $payload);
        } catch (RequestException $e) {
            $resp = $e->response;
            if (
                $resp->status() === 403
                && str_contains(strtolower($resp->body()), 'signature')
                && $this->merchantKey !== ''
                && $this->merchantKey !== $this->apiKey
            ) {
                Log::warning('[Duitku] Signature api_key ditolak (403), mencoba fallback merchant_key', ['path' => $path]);
                $fallbackPayload = array_merge($basePayload, ['signature' => $this->sign($stringToSign, $this->merchantKey)]);

                return Http::timeout(20)->retry(2, 1000, $shouldRetry)->post($url, $fallbackPayload);
            }

            throw $e;
        }
    }

    /**
     * Uji koneksi & kredensial spesifik tanpa menyimpan ke DB terlebih dahulu.
     *
     * @return array{ok: bool, methods_count: int, message: string, used_fallback_key: bool}
     */
    public function testConnection(string $mode, string $merchantCode, string $apiKey, string $merchantKey = ''): array
    {
        $mc = trim($merchantCode);
        $ak = trim($apiKey) !== '' ? trim($apiKey) : trim($merchantKey);
        $mk = trim($merchantKey) !== '' ? trim($merchantKey) : $ak;

        if ($mc === '' || $ak === '') {
            return [
                'ok' => false,
                'methods_count' => 0,
                'message' => 'Merchant Code dan API Key wajib diisi sebelum uji koneksi.',
                'used_fallback_key' => false,
            ];
        }

        $baseUrl = $mode === 'production'
            ? config('duitku.production_url')
            : config('duitku.sandbox_url');
        $datetime = date('Y-m-d H:i:s');
        $amount = 10000;
        $stringToSign = $mc.$amount.$datetime;
        $payload = [
            'merchantCode' => $mc,
            'amount' => $amount,
            'datetime' => $datetime,
            'signature' => $this->sign($stringToSign, $ak),
        ];

        $usedFallback = false;
        try {
            $response = Http::timeout(15)->post($baseUrl.'/paymentmethod/getpaymentmethod', $payload);
            if ($response->status() === 403 && str_contains(strtolower($response->body()), 'signature') && $mk !== '' && $mk !== $ak) {
                $payload['signature'] = $this->sign($stringToSign, $mk);
                $response = Http::timeout(15)->post($baseUrl.'/paymentmethod/getpaymentmethod', $payload);
                $usedFallback = $response->ok();
            }

            $data = $response->json() ?? [];
            if (! $response->ok() || ! $this->isSuccessResponse($data)) {
                $err = $this->pickField($data, 'message')
                    ?: $this->pickField($data, 'Message')
                    ?: ('HTTP '.$response->status());

                return [
                    'ok' => false,
                    'methods_count' => 0,
                    'message' => 'Gagal terhubung ke Duitku ('.strtoupper($mode).'): '.$err,
                    'used_fallback_key' => false,
                ];
            }

            $count = count($this->pickField($data, 'paymentFee') ?? []);

            return [
                'ok' => true,
                'methods_count' => $count,
                'message' => $usedFallback
                    ? 'Terhubung ke Duitku ('.strtoupper($mode)."), {$count} metode tersedia — catatan: Merchant Key valid, perbarui field API Key agar sama."
                    : 'Terhubung ke Duitku ('.strtoupper($mode)."), {$count} metode pembayaran aktif tersedia.",
                'used_fallback_key' => $usedFallback,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'methods_count' => 0,
                'message' => 'Tidak dapat menjangkau server Duitku: '.$e->getMessage(),
                'used_fallback_key' => false,
            ];
        }
    }

    /**
     * Sukses bila statusCode ATAU responseCode (lama) === '00'.
     * Key dicocokkan case-insensitive (respons historis pernah beda kapitalisasi).
     */
    private function isSuccessResponse(array $data): bool
    {
        $code = $this->pickField($data, 'statusCode') ?? $this->pickField($data, 'responseCode');

        return $code !== null && (string) $code === '00';
    }

    /** Ambil nilai dari array dgn key case-insensitive (tanpa merusak key asli). */
    private function pickField(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        foreach ($data as $k => $v) {
            if (is_string($k) && strcasecmp($k, $key) === 0) {
                return $v;
            }
        }

        return null;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function getSignatureMode(): string
    {
        return $this->signatureMode;
    }
}
