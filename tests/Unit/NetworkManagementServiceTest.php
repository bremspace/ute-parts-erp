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
}
