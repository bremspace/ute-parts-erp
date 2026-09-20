<?php

namespace App\Modules\Marketplace\Services;

use Illuminate\Support\Facades\Http;

/**
 * Duitku Payment Gateway (PRD §4.7).
 * Alur: createTransaction → simpan reference/merchantOrderId → redirect/popup ke halaman Duitku.
 * Webhook POST /webhook/duitku wajib verify signature + idempotent (di PaymentController).
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

    public function __construct()
    {
        $this->merchantCode = config('duitku.merchant_code');
        $this->apiKey = config('duitku.api_key');
        $this->merchantKey = config('duitku.merchant_key');
        $this->sandbox = (bool) config('duitku.sandbox');
        $this->baseUrl = $this->sandbox
            ? config('duitku.sandbox_url')
            : config('duitku.production_url');
    }

    public function isConfigured(): bool
    {
        return $this->merchantCode !== '' && $this->apiKey !== '' && $this->merchantKey !== '';
    }

    /**
     * Buat transaksi Duitku.
     *
     * @param string $merchantOrderId  no_transaksi lokal
     * @param float $amount
     * @param string $customerName
     * @param string|null $customerEmail
     * @param string|null $phoneNumber
     * @param string|null $paymentMethod  VA|QRIS|OVO|... (null = ambil dari getPaymentMethods)
     * @param string|null $callbackUrl
     * @param string|null $returnUrl
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
        $payload = [
            'merchantCode' => $this->merchantCode,
            'paymentAmount' => (int) round($amount),
            'paymentMethod' => $paymentMethod,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => 'Order Ute Parts ' . $merchantOrderId,
            'email' => $customerEmail,
            'phoneNumber' => $phoneNumber,
            'customerVaName' => $customerName ?: 'Customer',
            'callbackUrl' => $callbackUrl ?: url('/webhook/duitku'),
            'returnUrl' => $returnUrl ?: url('/checkout'),
            'expiryPeriod' => config('duitku.expiry_period'),
            'signature' => $this->makeSignature($merchantOrderId, $amount),
        ];

        $response = Http::timeout(20)->post($this->baseUrl . '/createPaymentMethodV2', $payload);

        $data = $response->json();

        if (isset($data['responseCode']) && (string) $data['responseCode'] !== '00') {
            throw new \Exception('Duitku: ' . ($data['message'] ?? 'Transaksi gagal dibuat'));
        }

        return [
            'merchant_order_id' => $data['merchantOrderId'] ?? $merchantOrderId,
            'reference' => $data['reference'] ?? null,
            'payment_url' => $data['paymentUrl'] ?? null,
            'va_number' => $data['vaNumber'] ?? null,
            'qr_string' => $data['qrString'] ?? null,
            'expiry' => $data['expiryTime'] ?? null,
        ];
    }

    /**
     * Signature MD5 untuk createTransaction:
     * md5(merchantCode + merchantOrderId + amount + apiKey)
     */
    private function makeSignature(string $merchantOrderId, float $amount): string
    {
        return md5($this->merchantCode . $merchantOrderId . (int) round($amount) . $this->apiKey);
    }

    /**
     * Verify signature callback webhook Duitku:
     * md5(merchantCode + amount + merchantOrderId + apiKey)
     */
    public function verifyCallbackSignature(int $amount, string $merchantOrderId, string $signature): bool
    {
        $expected = md5($this->merchantCode . (int) $amount . $merchantOrderId . $this->apiKey);
        return hash_equals($expected, $signature);
    }

    /**
     * Daftar metode pembayaran aktif dari Duitku (ambil dinamis, jangan hardcode).
     */
    public function getPaymentMethods(float $amount): array
    {
        $payload = [
            'merchantCode' => $this->merchantCode,
            'amount' => (int) round($amount),
            'signature' => md5($this->merchantCode . (int) round($amount) . $this->apiKey),
        ];

        $response = Http::timeout(15)->post($this->baseUrl . '/getPaymentMethodV2', $payload);
        $methods = collect($response->json('paymentFee') ?? []);

        return $methods->map(fn ($m) => [
            'kode' => $m['paymentMethod'] ?? null,
            'nama' => $m['paymentMethodName'] ?? null,
            'fee' => $m['totalFee'] ?? 0,
        ])->values()->toArray();
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}