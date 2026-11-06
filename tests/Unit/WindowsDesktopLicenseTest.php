<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Scripts\License\HardwareIdentifier;
use Scripts\License\LicenseVerifier;

class WindowsDesktopLicenseTest extends TestCase
{
    private string $tempLicensePath;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/scripts/license/HardwareIdentifier.php';
        require_once dirname(__DIR__, 2).'/scripts/license/LicenseVerifier.php';

        $this->tempLicensePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_bremspace_license_'.uniqid().'.key';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLicensePath)) {
            @unlink($this->tempLicensePath);
        }
        putenv('BREMSPACE_LICENSE_PATH');
        parent::tearDown();
    }

    public function test_custom_license_path_resolution_via_env(): void
    {
        putenv("BREMSPACE_LICENSE_PATH={$this->tempLicensePath}");

        $resolvedPath = LicenseVerifier::getLicenseFilePath();
        $this->assertSame($this->tempLicensePath, $resolvedPath);
    }

    public function test_license_verification_lifecycle_on_custom_desktop_path(): void
    {
        putenv("BREMSPACE_LICENSE_PATH={$this->tempLicensePath}");

        $challenge = HardwareIdentifier::getChallengeCode();
        $this->assertStringStartsWith('BREM-', $challenge);

        // Before license exists
        $this->assertFalse(LicenseVerifier::isLicensed());

        // Generate valid activation key for current hardware challenge
        $key = LicenseVerifier::computeSignature($challenge);

        $saved = LicenseVerifier::saveLicense($key);
        $this->assertTrue($saved);
        $this->assertFileExists($this->tempLicensePath);

        // After valid activation
        $this->assertTrue(LicenseVerifier::isLicensed());
    }

    public function test_host_challenge_resolution_from_env_or_license_key(): void
    {
        putenv("BREMSPACE_LICENSE_PATH={$this->tempLicensePath}");
        $mockChallenge = 'BREM-ABCD-1234-5678';
        putenv("BREMSPACE_HOST_CHALLENGE={$mockChallenge}");

        $resolved = HardwareIdentifier::getHostChallenge();
        $this->assertSame($mockChallenge, $resolved);

        putenv('BREMSPACE_HOST_CHALLENGE');
        unset($_ENV['BREMSPACE_HOST_CHALLENGE'], $_SERVER['BREMSPACE_HOST_CHALLENGE']);

        // Write valid license key with mock challenge
        $validKey = LicenseVerifier::computeSignature($mockChallenge);
        $payload = [
            'status' => 'ACTIVE',
            'challenge' => $mockChallenge,
            'key' => $validKey,
            'verified_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($this->tempLicensePath, json_encode($payload));

        $fallbackChallenge = HardwareIdentifier::getHostChallenge();
        $this->assertSame($mockChallenge, $fallbackChallenge);
    }

    public function test_raw_hardware_identifier_returns_non_empty_string(): void
    {
        $raw = HardwareIdentifier::getRawHardwareIdentifier();
        $this->assertNotEmpty($raw);
        $this->assertIsString($raw);

        $machineHash = HardwareIdentifier::getMachineHash();
        $this->assertSame(64, strlen($machineHash)); // SHA-256 is 64 hex characters

        $challenge = HardwareIdentifier::getChallengeCode();
        $this->assertMatchesRegularExpression('/^BREM-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $challenge);
    }
}
