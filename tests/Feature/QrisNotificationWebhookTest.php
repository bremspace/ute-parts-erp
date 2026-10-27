<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Crm\Services\KonfigurasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QrisNotificationWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('qris_payment_50000');
        Cache::forget('qris_payment_75000');
        Cache::forget('qris_payment_85000');
        Cache::forget('qris_payment_99000');
        Cache::forget('qris_payment_125000');
        Cache::forget('qris_payment_250000');
        Cache::forget('qris_payment_1500000');
        Cache::forget('qris_latest_payment');
        parent::tearDown();
    }

    public function test_webhook_receives_direct_nominal(): void
    {
        $response = $this->postJson('/api/webhook/qris-notif', [
            'nominal' => 75000,
            'title' => 'BCA Mobile',
            'text' => 'QRIS Rp 75.000 berhasil diterima',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nominal' => 75000,
            ],
        ]);

        $cached = Cache::get('qris_payment_75000');
        $this->assertNotNull($cached);
        $this->assertSame(75000, $cached['nominal']);

        // Check status polling
        $statusResp = $this->getJson('/api/qris/check-status?nominal=75000');
        $statusResp->assertStatus(200);
        $statusResp->assertJson([
            'paid' => true,
        ]);
    }

    public function test_webhook_parses_nominal_from_raw_notification_text(): void
    {
        $response = $this->postJson('/api/webhook/qris-notif', [
            'title' => 'GoPay Merchant',
            'text' => 'Pembayaran QRIS sebesar Rp 125.000 dari SITI AISYAH telah diterima.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nominal' => 125000,
            ],
        ]);

        $cached = Cache::get('qris_payment_125000');
        $this->assertNotNull($cached);
        $this->assertSame(125000, $cached['nominal']);
    }

    public function test_webhook_enforces_secret_when_configured(): void
    {
        $configService = app(KonfigurasiService::class);
        $configService->set('qris_webhook_secret', 'my-super-secret-123');

        // Request without secret -> 401
        $badResp = $this->postJson('/api/webhook/qris-notif', [
            'nominal' => 50000,
            'text' => 'QRIS 50000',
        ]);
        $badResp->assertStatus(401);

        // Request with secret in header -> 200
        $goodResp = $this->withHeaders([
            'X-QRIS-Secret' => 'my-super-secret-123',
        ])->postJson('/api/webhook/qris-notif', [
            'nominal' => 50000,
            'text' => 'QRIS 50000',
        ]);
        $goodResp->assertStatus(200);

        // History log assertion
        $logs = Cache::get('qris_webhook_log_history');
        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs);
        $this->assertSame('sukses', $logs[0]['status']);
        $this->assertSame(50000, $logs[0]['nominal']);

        // Clean up
        $configService->set('qris_webhook_secret', '');
    }

    public function test_webhook_parses_nominal_with_sen_decimal_suffix(): void
    {
        // Notifikasi umum bank seperti BCA / Mandiri menyertakan desimal sen ,00
        $response = $this->postJson('/api/webhook/qris-notif', [
            'title' => 'BCA Mobile',
            'text' => 'Transaksi QRIS sebesar Rp 50.000,00 dari BUDI SANTOSO berhasil.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nominal' => 50000,
            ],
        ]);

        $cached = Cache::get('qris_payment_50000');
        $this->assertNotNull($cached);
        $this->assertSame(50000, $cached['nominal']);

        // Uji nominal jutaan dengan sen
        $response2 = $this->postJson('/api/webhook/qris-notif', [
            'title' => 'Livin Mandiri',
            'text' => 'Dana masuk Rp 1.500.000,00 dari QRIS TOKO.',
        ]);

        $response2->assertStatus(200);
        $this->assertSame(1500000, $response2->json('data.nominal'));
        $this->assertNotNull(Cache::get('qris_payment_1500000'));
    }

    public function test_webhook_handles_get_request_query_params(): void
    {
        $response = $this->getJson('/api/webhook/qris-notif?title=BCA&text=Transaksi+QRIS+sebesar+Rp+85.000,00+sukses');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nominal' => 85000,
            ],
        ]);

        $this->assertNotNull(Cache::get('qris_payment_85000'));
    }

    public function test_webhook_handles_macrodroid_not_body_parameter(): void
    {
        $response = $this->postJson('/api/webhook/qris-notif', [
            'not_title' => 'Livin Mandiri',
            'not_body' => 'Transfer masuk Sebesar Rp 99.000 dari QRIS DANA',
        ]);

        $response->assertStatus(200);
        $this->assertSame(99000, $response->json('data.nominal'));
        $this->assertNotNull(Cache::get('qris_payment_99000'));
    }

    public function test_webhook_handles_raw_text_payload(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhook/qris-notif',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '01/10 14:22:10 TRSF E-BANKING CR 250.000,00 DARI QRIS'
        );

        $response->assertStatus(200);
        $this->assertSame(250000, $response->json('data.nominal'));
        $this->assertNotNull(Cache::get('qris_payment_250000'));
    }
}
