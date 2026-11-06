<?php

namespace Tests\Unit;

use App\Modules\Rbac\Services\AppUpdateService;
use App\Modules\Rbac\Services\NetworkManagementService;
use PHPUnit\Framework\TestCase;

class NetworkManagementServiceTest extends TestCase
{
    public function test_get_status_returns_valid_structure(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $service = new NetworkManagementService($updateService);

        $status = $service->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('network_mode', $status);
        $this->assertArrayHasKey('app_url', $status);
        $this->assertArrayHasKey('is_online', $status);
        $this->assertArrayHasKey('lan_ip', $status);
        $this->assertArrayHasKey('cloudflared_installed', $status);
        $this->assertArrayHasKey('cloudflared_running', $status);
        $this->assertArrayHasKey('tunnel_token', $status);
    }

    public function test_upgrade_validates_domain(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $service = new NetworkManagementService($updateService);

        $result = $service->upgradeToOnline('invalid domain');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Format domain tidak valid', $result['message']);
    }

    public function test_upgrade_accepts_valid_domain_and_updates_env(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $updateService->expects($this->once())
            ->method('updateEnv')
            ->with($this->callback(function ($values) {
                return isset($values['APP_URL'])
                    && $values['APP_URL'] === 'https://pos.tokoberkah.com'
                    && isset($values['NETWORK_MODE'])
                    && $values['NETWORK_MODE'] === 'online'
                    && isset($values['CLOUDFLARE_TUNNEL_TOKEN'])
                    && $values['CLOUDFLARE_TUNNEL_TOKEN'] === 'test-token-123';
            }))
            ->willReturn(true);

        $service = new NetworkManagementService($updateService);
        $result = $service->upgradeToOnline('https://pos.tokoberkah.com/', 'cloudflare', 'test-token-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('https://pos.tokoberkah.com', $result['app_url']);
    }

    public function test_revert_to_lan_updates_env(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $updateService->expects($this->once())
            ->method('updateEnv')
            ->with($this->callback(function ($values) {
                return isset($values['APP_URL'])
                    && $values['APP_URL'] === 'http://192.168.1.50:1741'
                    && isset($values['NETWORK_MODE'])
                    && $values['NETWORK_MODE'] === 'lan';
            }))
            ->willReturn(true);

        $service = new NetworkManagementService($updateService);
        $result = $service->revertToLan('http://192.168.1.50:1741');

        $this->assertTrue($result['success']);
    }

    public function test_get_device_access_urls_returns_valid_structure_and_qr(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $service = new NetworkManagementService($updateService);

        $urls = $service->getDeviceAccessUrls();

        $this->assertIsArray($urls);
        $this->assertArrayHasKey('lan_ip', $urls);
        $this->assertArrayHasKey('port', $urls);
        $this->assertArrayHasKey('lan_url', $urls);
        $this->assertArrayHasKey('lan_qr_svg', $urls);
        $this->assertArrayHasKey('is_online', $urls);
        $this->assertArrayHasKey('recommended_url', $urls);
        $this->assertArrayHasKey('recommended_qr_svg', $urls);

        $this->assertStringStartsWith('http://', $urls['lan_url']);
        $this->assertStringEndsWith('/app', $urls['lan_url']);
        $this->assertStringContainsString('<svg', $urls['lan_qr_svg']);
        $this->assertStringContainsString('</svg>', $urls['lan_qr_svg']);
    }

    public function test_normalize_domain_and_parse_parts(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $service = new NetworkManagementService($updateService);

        $this->assertEquals('pos.tokoberkah.com', $service->normalizeDomain('https://pos.tokoberkah.com:8443/shop'));
        $this->assertEquals('tokosaya.id', $service->normalizeDomain('HTTP://TOKOSAYA.ID/'));

        $partsSub = $service->parseDomainParts('pos.tokoberkah.com');
        $this->assertEquals('pos', $partsSub['host']);
        $this->assertEquals('tokoberkah.com', $partsSub['root_domain']);

        $partsApex = $service->parseDomainParts('tokoberkah.com');
        $this->assertEquals('@', $partsApex['host']);
        $this->assertEquals('tokoberkah.com', $partsApex['root_domain']);
    }

    public function test_check_domain_dns_validates_and_formats_instructions(): void
    {
        $updateService = $this->createMock(AppUpdateService::class);
        $service = new NetworkManagementService($updateService);

        // Invalid domain
        $invalid = $service->checkDomainDns('invalid_not_domain');
        $this->assertFalse($invalid['is_pointed']);
        $this->assertEquals('invalid_domain', $invalid['status']);

        // Valid domain with Cloudflare tunnel token decode
        $tokenPayload = base64_encode(json_encode(['a' => 'acc123', 't' => 'uuid-tunnel-xyz', 's' => 'sec456']));
        $valid = $service->checkDomainDns('kasir.tokoberkah.com', $tokenPayload);

        $this->assertEquals('kasir.tokoberkah.com', $valid['domain']);
        $this->assertEquals('kasir', $valid['host']);
        $this->assertEquals('tokoberkah.com', $valid['root_domain']);
        $this->assertIsArray($valid['instructions']);
        $this->assertEquals('CNAME', $valid['instructions']['type']);
        $this->assertEquals('kasir', $valid['instructions']['name']);
        $this->assertEquals('uuid-tunnel-xyz.cfargotunnel.com', $valid['instructions']['target']);
    }
}
