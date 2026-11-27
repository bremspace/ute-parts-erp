<?php

namespace Tests\Feature;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\WhatsAppGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function setConfig(array $config): void
    {
        app(KonfigurasiService::class)->set('wa_gateway_config', $config);
    }

    public function test_openwa_mengirim_ke_endpoint_send_text_dengan_api_key(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785/api',
            'token' => 'rahasia-openwa-key',
            'session' => 'toko-1',
        ]);

        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $hasil = app(WhatsAppGatewayService::class)->kirim('081234567890', 'Halo dari ERP');

        $this->assertTrue($hasil['ok']);
        $this->assertSame('6281234567890@c.us', $hasil['chat_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/sessions/toko-1/messages/send-text')
                && $request->hasHeader('X-API-Key')
                && $request->header('X-API-Key')[0] === 'rahasia-openwa-key'
                && $request['chatId'] === '6281234567890@c.us'
                && $request['text'] === 'Halo dari ERP';
        });
    }

    public function test_chat_id_menormalisasi_semua_varian_nomor(): void
    {
        $this->assertSame('6281234567890@c.us', WhatsAppGatewayService::chatId('081234567890'));
        $this->assertSame('6281234567890@c.us', WhatsAppGatewayService::chatId('6281234567890'));
        $this->assertSame('6281234567890@c.us', WhatsAppGatewayService::chatId('81234567890'));
        $this->assertSame('6281234567890@c.us', WhatsAppGatewayService::chatId('+62 812-3456-7890'));
        $this->assertNull(WhatsAppGatewayService::chatId('budi'));
        $this->assertNull(WhatsAppGatewayService::chatId(null));
    }

    public function test_openwa_error_tidak_bocorkan_api_key(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785/api',
            'token' => 'rahasia-openwa-key',
            'session' => 'toko-1',
        ]);

        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

        $hasil = app(WhatsAppGatewayService::class)->kirim('081234567890', 'Halo');

        $this->assertFalse($hasil['ok']);
        $this->assertStringNotContainsString('rahasia-openwa-key', (string) $hasil['error']);
        $this->assertStringContainsString('401', (string) $hasil['error']);
    }

    public function test_provider_lama_tetap_memakai_authorization_header(): void
    {
        $this->setConfig([
            'provider' => 'fonnte',
            'url' => 'https://api.fonnte.com/send',
            'token' => 'fonnte-token',
        ]);

        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $hasil = app(WhatsAppGatewayService::class)->kirim('081234567890', 'Halo');

        $this->assertTrue($hasil['ok']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->header('Authorization')[0] === 'fonnte-token'
                && $request['target'] === '6281234567890'
                && $request['message'] === 'Halo';
        });
    }

    public function test_konfigurasi_kosong_diberi_error_yang_jelas(): void
    {
        $this->setConfig(['provider' => 'openwa', 'url' => null, 'token' => null]);

        $hasil = app(WhatsAppGatewayService::class)->kirim('081234567890', 'Halo');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum dikonfigurasi', (string) $hasil['error']);
    }

    public function test_job_menandai_terkirim_saat_openwa_sukses(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785/api',
            'token' => 'rahasia',
            'session' => 'default',
        ]);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $log = NotifikasiKeluar::create([
            'tipe' => 'wa',
            'tujuan' => '081234567890',
            'judul' => 'Tes',
            'konten' => 'Halo',
            'status' => 'pending',
        ]);

        KirimNotifikasiJob::dispatchSync($log->id);

        $log->refresh();
        $this->assertSame('terkirim', $log->status);
        $this->assertNull($log->error);
    }

    public function test_job_menandai_gagal_saat_openwa_error(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785/api',
            'token' => 'rahasia',
            'session' => 'default',
        ]);

        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $log = NotifikasiKeluar::create([
            'tipe' => 'wa',
            'tujuan' => '081234567890',
            'judul' => 'Tes',
            'konten' => 'Halo',
            'status' => 'pending',
        ]);

        KirimNotifikasiJob::dispatchSync($log->id);

        $log->refresh();
        $this->assertSame('gagal', $log->status);
        $this->assertStringContainsString('500', (string) $log->error);
        $this->assertStringNotContainsString('rahasia', (string) $log->error);
    }

    public function test_normalisasi_url_openwa_memastikan_akhiran_api_idempoten(): void
    {
        $this->assertSame('http://127.0.0.1:2785/api', WhatsAppGatewayService::normalizeOpenWaUrl('http://127.0.0.1:2785'));
        $this->assertSame('http://127.0.0.1:2785/api', WhatsAppGatewayService::normalizeOpenWaUrl('http://127.0.0.1:2785/api'));
        $this->assertSame('http://127.0.0.1:2785/api', WhatsAppGatewayService::normalizeOpenWaUrl('http://127.0.0.1:2785/api/'));
    }

    public function test_config_mendekripsi_token_db_dan_fallback_ke_services_openwa(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785',
            'token' => Crypt::encryptString('token-terenkripsi-123'),
            'session' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $cfg = app(WhatsAppGatewayService::class)->config();
        $this->assertSame('token-terenkripsi-123', $cfg['token']);

        DB::table('konfigurasi')->where('kunci', 'wa_gateway_config')->delete();
        Cache::forget('crm-konfigurasi:wa_gateway_config');
        config([
            'services.openwa.url' => 'http://127.0.0.1:2785/api',
            'services.openwa.api_key' => 'env-openwa-key',
            'services.openwa.session' => 'uuid-env-session',
        ]);

        $fallback = app(WhatsAppGatewayService::class)->config();
        $this->assertSame('openwa', $fallback['provider']);
        $this->assertSame('env-openwa-key', $fallback['token']);
        $this->assertSame('uuid-env-session', $fallback['session']);
    }

    public function test_openwa_409_dan_503_diberi_pesan_jelas_serta_job_release_untuk_retry(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785',
            'token' => 'rahasia',
            'session' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        Http::fakeSequence()
            ->push(['error' => 'not ready'], 409)
            ->push(['error' => 'restarting'], 503);

        $svc = app(WhatsAppGatewayService::class);
        $r409 = $svc->kirim('081234567890', 'Tes 409');
        $this->assertFalse($r409['ok']);
        $this->assertStringContainsString('409', (string) $r409['error']);
        $this->assertTrue((bool) ($r409['retryable'] ?? false));

        $log = NotifikasiKeluar::create([
            'tipe' => 'wa',
            'tujuan' => '081234567890',
            'judul' => 'Tes Retry',
            'konten' => 'Halo',
            'status' => 'pending',
        ]);

        KirimNotifikasiJob::dispatchSync($log->id);

        $log->refresh();
        $this->assertSame('pending', $log->status);
        $this->assertStringContainsString('503', (string) $log->error);
    }

    public function test_openwa_html_atau_success_false_http_200_ditolak(): void
    {
        $this->setConfig([
            'provider' => 'openwa',
            'url' => 'http://127.0.0.1:2785/api',
            'token' => 'rahasia',
            'session' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        Http::fakeSequence()
            ->push('<html><body>Login</body></html>', 200, ['Content-Type' => 'text/html'])
            ->push(['success' => false, 'message' => 'Session disconnected'], 200);

        $svc = app(WhatsAppGatewayService::class);
        $html = $svc->kirim('081234567890', 'Tes HTML');
        $this->assertFalse($html['ok']);
        $this->assertFalse($html['retryable']);

        $jsonFalse = $svc->kirim('081234567890', 'Tes False');
        $this->assertFalse($jsonFalse['ok']);
        $this->assertFalse($jsonFalse['retryable']);
    }
}
