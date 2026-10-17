<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Services\DuitkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * [T-04] Uji DuitkuService via Http::fake — TANPA panggil API nyata.
 *
 * Kontrak docs.duitku.com (30 Sep 2026):
 *  - create  → POST /v2/inquiry, signature = HMAC-SHA256(merchantCode+merchantOrderId+paymentAmount, apiKey)
 *  - methods → POST /paymentmethod/getpaymentmethod, signature = HMAC-SHA256(merchantCode+paymentAmount+datetime, apiKey)
 *  - status  → POST /transactionStatus, signature = HMAC-SHA256(merchantCode+merchantOrderId, apiKey)
 */
class DuitkuServiceTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT_CODE = 'TST001';

    private const API_KEY = 'testapikey123';

    private const BASE = 'https://sandbox.duitku.com/webapi/api/merchant';

    private function seedConfig(string $mode = 'dual'): DuitkuService
    {
        config([
            'duitku.merchant_code' => self::MERCHANT_CODE,
            'duitku.api_key' => self::API_KEY,
            'duitku.merchant_key' => 'testkey456',
            'duitku.sandbox' => true,
            'duitku.signature_mode' => $mode,
            'duitku.expiry_period' => 1440,
        ]);

        return app(DuitkuService::class);
    }

    // ===== createTransaction =====

    public function test_create_transaction_posts_to_v2_inquiry_dengan_signature_hmac_dan_payload_benar(): void
    {
        $svc = $this->seedConfig('hmac');

        Http::fake([
            self::BASE.'/v2/inquiry' => Http::response([
                'statusCode' => '00',
                'reference' => 'REF123',
                'paymentUrl' => 'https://checkout.duitku.com/abc',
                'vaNumber' => '1234567890',
                'qrString' => 'qr-data',
                'amount' => 100000,
                'statusMessage' => 'Success',
            ]),
        ]);

        $result = $svc->createTransaction('ORD-1', 100000, 'Budi', 'budi@test.id', '08120000001', 'VA');

        // Endpoint sesuai docs baru
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/v2/inquiry')
                && $request->method() === 'POST';
        });

        // Signature HMAC-SHA256 known vector: hmac-sha256('TST001'.'ORD-1'.'100000', 'testapikey123')
        $expectedSignature = '94a5734c514d9d22e82421c5b097db7cd810a7ccf8d2bef9c70511da43e4a22e';
        Http::assertSent(function ($request) use ($expectedSignature) {
            $data = $request->data();

            return $data['signature'] === $expectedSignature
                && $data['merchantCode'] === self::MERCHANT_CODE
                && $data['merchantOrderId'] === 'ORD-1'
                && $data['paymentAmount'] === 100000
                && $data['paymentMethod'] === 'VA'
                && $data['customerVaName'] === 'Budi'
                && $data['email'] === 'budi@test.id'
                && $data['expiryPeriod'] === 1440
                && str_contains($data['callbackUrl'], '/webhook/duitku');
        });

        // Parsing response sukses
        $this->assertSame('REF123', $result['reference']);
        $this->assertSame('https://checkout.duitku.com/abc', $result['payment_url']);
        $this->assertSame('1234567890', $result['va_number']);
        $this->assertSame('qr-data', $result['qr_string']);
        $this->assertSame('ORD-1', $result['merchant_order_id']);
    }

    public function test_create_transaction_menerima_response_lama_responsecode_00(): void
    {
        $svc = $this->seedConfig();

        Http::fake([
            self::BASE.'/v2/inquiry' => Http::response([
                'responseCode' => '00',
                'reference' => 'REF-LEGACY',
                'paymentUrl' => 'https://checkout.duitku.com/legacy',
            ]),
        ]);

        $result = $svc->createTransaction('ORD-2', 50000, 'Siti');

        $this->assertSame('REF-LEGACY', $result['reference']);
        $this->assertSame('https://checkout.duitku.com/legacy', $result['payment_url']);
    }

    public function test_create_transaction_gagal_selain_00_melempar_exception_indonesia(): void
    {
        $svc = $this->seedConfig();

        Http::fake([
            self::BASE.'/v2/inquiry' => Http::response([
                'statusCode' => '01',
                'statusMessage' => 'Jumlah transaksi tidak valid',
            ]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Jumlah transaksi tidak valid/u');

        $svc->createTransaction('ORD-3', 100, 'Budi');
    }

    public function test_create_transaction_mode_md5_pakai_signature_md5_legacy(): void
    {
        $svc = $this->seedConfig('md5');

        Http::fake([
            self::BASE.'/v2/inquiry' => Http::response(['statusCode' => '00', 'reference' => 'R', 'paymentUrl' => 'u']),
        ]);

        $svc->createTransaction('ORD-1', 100000, 'Budi');

        // md5('TST001'.'ORD-1'.'100000'.'testapikey123')
        $expectedMd5 = '8ecc9c0ec7f8ce84229cc73324d87fea';
        Http::assertSent(fn ($request) => $request->data()['signature'] === $expectedMd5);
    }

    // ===== getPaymentMethods =====

    public function test_get_payment_methods_posts_endpoint_baru_dengan_signature_datetime(): void
    {
        $svc = $this->seedConfig('hmac');

        Http::fake([
            self::BASE.'/paymentmethod/getpaymentmethod' => Http::response([
                'responseCode' => '00',
                'paymentFee' => [
                    ['paymentMethod' => 'VA', 'paymentName' => 'BCA Virtual Account', 'totalFee' => 4000],
                    ['paymentMethod' => 'TQ', 'paymentName' => 'QRIS', 'totalFee' => 700],
                ],
            ]),
        ]);

        $methods = $svc->getPaymentMethods(100000);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_ends_with($request->url(), '/paymentmethod/getpaymentmethod')
                && $data['merchantCode'] === self::MERCHANT_CODE
                // Uji live sandbox 2026-10-17: field bernama 'amount' (bukan
                // 'paymentAmount' spt tertulis di docs) — server hanya membaca ini
                && $data['amount'] === 100000
                && isset($data['datetime'])
                // signature = hmac-sha256(merchantCode+amount+datetime, apiKey)
                && hash_equals(
                    hash_hmac('sha256', self::MERCHANT_CODE.$data['amount'].$data['datetime'], self::API_KEY),
                    $data['signature']
                );
        });

        $this->assertSame([
            [
                'kode' => 'TQ',
                'nama' => 'QRIS (Universal Semua Bank & E-Wallet)',
                'fee' => 700,
                'kategori' => 'qris',
                'badge' => 'QRIS',
                'deskripsi' => 'Scan via BCA, Mandiri, BRI, BNI, DANA, GoPay, OVO, ShopeePay',
            ],
            [
                'kode' => 'VA',
                'nama' => 'Maybank Virtual Account',
                'fee' => 4000,
                'kategori' => 'va',
                'badge' => 'Maybank',
                'deskripsi' => 'M2U ID App, ATM Maybank',
            ],
        ], $methods);
    }

    public function test_get_payment_methods_response_error_melempar_exception(): void
    {
        $svc = $this->seedConfig();

        Http::fake([
            self::BASE.'/paymentmethod/getpaymentmethod' => Http::response([
                'responseCode' => '01',
                'message' => 'Merchant tidak valid',
            ]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Merchant tidak valid/u');

        $svc->getPaymentMethods(100000);
    }

    // ===== transactionStatus =====

    public function test_transaction_status_endpoint_dan_signature(): void
    {
        $svc = $this->seedConfig('hmac');

        Http::fake([
            self::BASE.'/transactionStatus' => Http::response([
                'statusCode' => '00',
                'reference' => 'REF-S',
                'amount' => 100000,
                'fee' => 4000,
            ]),
        ]);

        $status = $svc->transactionStatus('ORD-1');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_ends_with($request->url(), '/transactionStatus')
                && $data['merchantOrderId'] === 'ORD-1'
                && hash_equals(
                    hash_hmac('sha256', self::MERCHANT_CODE.'ORD-1', self::API_KEY),
                    $data['signature']
                );
        });

        $this->assertSame('sukses', $status['status']);
        $this->assertSame('REF-S', $status['reference']);
        $this->assertSame(100000, $status['amount']);
        $this->assertSame(4000, $status['fee']);
    }

    public function test_transaction_status_mapping_kode(): void
    {
        $svc = $this->seedConfig();

        // Sequence: satu stub, tiap request maju ke respons berikutnya (01 → 02 → 99)
        Http::fake([
            self::BASE.'/transactionStatus' => Http::sequence()
                ->push(['statusCode' => '01'])
                ->push(['statusCode' => '02'])
                ->push(['statusCode' => '99']),
        ]);

        $this->assertSame('pending', $svc->transactionStatus('ORD-1')['status']);
        $this->assertSame('batal', $svc->transactionStatus('ORD-1')['status']);
        $this->assertSame('unknown', $svc->transactionStatus('ORD-1')['status']);
    }

    public function test_transaction_status_network_error_kembalikan_error_bukan_throw(): void
    {
        $svc = $this->seedConfig();

        Http::fake([
            self::BASE.'/transactionStatus' => Http::response([], 500),
        ]);

        $status = $svc->transactionStatus('ORD-1');

        $this->assertSame('error', $status['status']);
    }

    // ===== verifyCallbackSignature (dual mode) =====

    public function test_verify_callback_hmac_diterima_di_mode_dual(): void
    {
        $svc = $this->seedConfig('dual');

        // hash_hmac('sha256', 'TST001'.'100000'.'ORD-1', 'testapikey123')
        $hmac = hash_hmac('sha256', 'TST001100000ORD-1', self::API_KEY);

        $this->assertTrue($svc->verifyCallbackSignature(100000, 'ORD-1', $hmac));
    }

    public function test_verify_callback_md5_lama_masih_diterima_di_mode_dual(): void
    {
        $svc = $this->seedConfig('dual');

        $md5 = md5('TST001100000ORD-1'.self::API_KEY);

        $this->assertTrue($svc->verifyCallbackSignature(100000, 'ORD-1', $md5));
    }

    public function test_verify_callback_mode_hmac_tolak_md5_dan_mode_md5_tolak_hmac(): void
    {
        $hmac = hash_hmac('sha256', 'TST001100000ORD-1', self::API_KEY);
        $md5 = md5('TST001100000ORD-1'.self::API_KEY);

        $hmacOnly = $this->seedConfig('hmac');
        $this->assertTrue($hmacOnly->verifyCallbackSignature(100000, 'ORD-1', $hmac));
        $this->assertFalse($hmacOnly->verifyCallbackSignature(100000, 'ORD-1', $md5));

        $md5Only = $this->seedConfig('md5');
        $this->assertTrue($md5Only->verifyCallbackSignature(100000, 'ORD-1', $md5));
        $this->assertFalse($md5Only->verifyCallbackSignature(100000, 'ORD-1', $hmac));
    }

    public function test_verify_callback_signature_salah_ditolak(): void
    {
        $svc = $this->seedConfig('dual');

        $this->assertFalse($svc->verifyCallbackSignature(100000, 'ORD-1', 'invalid-signature'));
        $this->assertFalse($svc->verifyCallbackSignature(100000, 'ORD-1', ''));
    }
}
