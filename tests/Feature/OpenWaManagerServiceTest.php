<?php

namespace Tests\Feature;

use App\Modules\Notifikasi\Services\OpenWaManagerService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenWaManagerServiceTest extends TestCase
{
    public function test_health_mengembalikan_ok_saat_openwa_siap(): void
    {
        Http::fake([
            'http://127.0.0.1:2785/api/health/ready' => Http::response(['status' => 'ok'], 200),
        ]);

        $res = app(OpenWaManagerService::class)->health('http://127.0.0.1:2785');

        $this->assertTrue($res['ok']);
        $this->assertNull($res['error']);
    }

    public function test_list_dan_normalisasi_sesi_dari_berbagai_bentuk_respons(): void
    {
        Http::fake([
            'http://127.0.0.1:2785/api/sessions' => Http::response([
                'data' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'name' => 'toko-utama',
                        'status' => 'ready',
                        'me' => ['id' => '628123456789@c.us'],
                    ],
                ],
            ], 200),
        ]);

        $res = app(OpenWaManagerService::class)->listSessions('http://127.0.0.1:2785/api', 'admin-secret-key');

        $this->assertTrue($res['ok']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $res['data'][0]['id']);
        $this->assertSame('toko-utama', $res['data'][0]['name']);
        $this->assertSame('628123456789', $res['data'][0]['phone']);
        $this->assertTrue($res['data'][0]['is_ready']);
    }

    public function test_create_operator_key_mengirim_dto_role_operator_dan_allowed_sessions(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        Http::fake([
            'http://127.0.0.1:2785/api/auth/api-keys' => Http::response([
                'id' => 'key-1',
                'apiKey' => 'owa_op_generated_token_123',
            ], 201),
        ]);

        $res = app(OpenWaManagerService::class)->createOperatorKey(
            'http://127.0.0.1:2785/api',
            'owa_admin_secret',
            $uuid
        );

        $this->assertTrue($res['ok']);
        $this->assertSame('owa_op_generated_token_123', $res['data']['apiKey']);

        Http::assertSent(function ($request) use ($uuid) {
            return $request->url() === 'http://127.0.0.1:2785/api/auth/api-keys'
                && $request->header('X-API-Key')[0] === 'owa_admin_secret'
                && $request['role'] === 'operator'
                && $request['allowedSessions'] === [$uuid]
                && ! array_key_exists('sessionId', $request->data());
        });
    }

    public function test_get_qr_membaca_field_qrcode_dari_openwa(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ';

        Http::fake([
            "http://127.0.0.1:2785/api/sessions/{$uuid}/qr" => Http::response([
                'qrCode' => $dataUrl,
                'status' => 'qr_ready',
            ], 200),
        ]);

        $res = app(OpenWaManagerService::class)->getQr('http://127.0.0.1:2785/api/', 'op-key', $uuid);

        $this->assertTrue($res['ok']);
        $this->assertSame($dataUrl, $res['data']['qr']);
        $this->assertSame('qr_ready', $res['data']['status']);
    }

    public function test_get_pairing_code_menormalisasi_nomor_indonesia(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        Http::fake([
            "http://127.0.0.1:2785/api/sessions/{$uuid}/pairing-code" => Http::response([
                'code' => 'ABCD-EFGH',
            ], 200),
        ]);

        $manager = app(OpenWaManagerService::class);
        $invalid = $manager->getPairingCode('http://127.0.0.1:2785/api', 'op-key', $uuid, 'bukan-nomor');
        $this->assertFalse($invalid['ok']);

        $valid = $manager->getPairingCode('http://127.0.0.1:2785/api', 'op-key', $uuid, '+62 812-3456-7890');
        $this->assertTrue($valid['ok']);
        $this->assertSame('ABCD-EFGH', $valid['data']['code']);

        Http::assertSent(fn ($request) => $request['phoneNumber'] === '6281234567890');
    }

    public function test_menolak_session_id_path_traversal_dan_tidak_bocor_api_key(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $manager = app(OpenWaManagerService::class);

        $badId = $manager->startSession('http://127.0.0.1:2785/api', 'secret-token-xyz', '../auth/api-keys');
        $this->assertFalse($badId['ok']);
        Http::assertNothingSent();

        $unauth = $manager->listSessions('http://127.0.0.1:2785/api', 'secret-token-xyz');
        $this->assertFalse($unauth['ok']);
        $this->assertStringNotContainsString('secret-token-xyz', (string) $unauth['error']);
        $this->assertStringContainsString('401', (string) $unauth['error']);
    }
}
