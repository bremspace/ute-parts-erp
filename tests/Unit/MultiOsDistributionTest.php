<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Scripts\License\HardwareIdentifier;

class MultiOsDistributionTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 2);
        require_once $this->rootDir.'/scripts/license/HardwareIdentifier.php';
    }

    public function test_linux_launcher_and_service_configs_exist_and_executable(): void
    {
        $launcher = $this->rootDir.'/tools/linux/launcher/bremspace-erp.sh';
        $this->assertFileExists($launcher);
        $this->assertTrue(is_executable($launcher), 'Linux launcher script must be executable');

        $content = (string) file_get_contents($launcher);
        $this->assertStringContainsString('HardwareIdentifier.php', $content);
        $this->assertStringContainsString('LicenseVerifier.php', $content);
        $this->assertStringContainsString('Caddyfile.linux', $content);

        $service = $this->rootDir.'/tools/linux/config/bremspace-erp.service';
        $this->assertFileExists($service);
        $serviceContent = (string) file_get_contents($service);
        $this->assertStringContainsString('[Unit]', $serviceContent);
        $this->assertStringContainsString('ExecStart=/opt/bremspace-erp/bin/bremspace-erp.sh', $serviceContent);

        $caddyfile = $this->rootDir.'/tools/linux/config/Caddyfile.linux';
        $this->assertFileExists($caddyfile);
        $this->assertStringContainsString('bind 0.0.0.0', (string) file_get_contents($caddyfile));
    }

    public function test_macos_launcher_and_configs_exist_and_executable(): void
    {
        $launcher = $this->rootDir.'/tools/macos/launcher/bremspace-launcher.sh';
        $this->assertFileExists($launcher);
        $this->assertTrue(is_executable($launcher), 'macOS launcher script must be executable');

        $content = (string) file_get_contents($launcher);
        $this->assertStringContainsString('HardwareIdentifier.php', $content);
        $this->assertStringContainsString('LicenseVerifier.php', $content);
        $this->assertStringContainsString('Caddyfile.macos', $content);

        $caddyfile = $this->rootDir.'/tools/macos/config/Caddyfile.macos';
        $this->assertFileExists($caddyfile);
        $this->assertStringContainsString('bind 0.0.0.0', (string) file_get_contents($caddyfile));
    }

    public function test_hardware_identifier_handles_multi_os_gracefully(): void
    {
        $raw = HardwareIdentifier::getRawHardwareIdentifier();
        $this->assertNotEmpty($raw);

        $challenge = HardwareIdentifier::getChallengeCode();
        $this->assertMatchesRegularExpression('/^BREM-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $challenge);
    }
}
