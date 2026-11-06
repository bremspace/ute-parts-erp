<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProprietaryAlgorithmService;
use PHPUnit\Framework\TestCase;
use Scripts\License\HardwareIdentifier;
use Scripts\License\LicenseVerifier;

class BytecodeProtectionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/scripts/license/HardwareIdentifier.php';
        require_once dirname(__DIR__, 2).'/scripts/license/LicenseVerifier.php';

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_protection_'.uniqid();
        @mkdir($this->tempDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services', 0777, true);
        @mkdir($this->tempDir.DIRECTORY_SEPARATOR.'routes', 0777, true);
        @mkdir($this->tempDir.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'license', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (! $items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_protect_code_script_encrypts_and_executes_php_files(): void
    {
        $sampleCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class ProprietaryAlgorithmService
{
    /**
     * Rahasia algoritma penentuan harga grosir
     */
    public function computeMargin(float $cost): float
    {
        return $cost * 1.35;
    }
}
PHP;

        $targetFile = $this->tempDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'ProprietaryAlgorithmService.php';
        file_put_contents($targetFile, $sampleCode);

        // Run protection script
        $protectScript = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'security'.DIRECTORY_SEPARATOR.'protect-code.php';
        $cmd = escapeshellcmd("php {$protectScript} ").escapeshellarg($this->tempDir);
        exec($cmd, $output, $returnCode);

        $this->assertSame(0, $returnCode, 'Protection script should exit with status 0');

        // Verify protected file contents
        $protectedContent = (string) file_get_contents($targetFile);
        $this->assertStringContainsString('Bremspace Protected Bytecode', $protectedContent);
        $this->assertStringNotContainsString('ProprietaryAlgorithmService', $protectedContent, 'Plain text class name should not exist');
        $this->assertStringNotContainsString('computeMargin', $protectedContent, 'Plain text method name should not exist');

        // Test execution in PHP runtime
        require_once $targetFile;
        $this->assertTrue(class_exists(ProprietaryAlgorithmService::class));

        $service = new ProprietaryAlgorithmService;
        $this->assertEquals(135.0, $service->computeMargin(100.0));
    }

    public function test_tampered_bytecode_is_rejected_by_integrity_check(): void
    {
        $sampleCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class TamperVictim
{
    public function status(): string
    {
        return "GENUINE";
    }
}
PHP;

        $targetFile = $this->tempDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'TamperVictim.php';
        file_put_contents($targetFile, $sampleCode);

        $protectScript = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'security'.DIRECTORY_SEPARATOR.'protect-code.php';
        exec(escapeshellcmd("php {$protectScript} ").escapeshellarg($this->tempDir), $out, $ret);

        $protectedContent = (string) file_get_contents($targetFile);
        $this->assertStringContainsString('Bremspace Protected Bytecode', $protectedContent);

        // Tamper by modifying one character in the payload
        preg_match("/return eval\(gzinflate\(base64_decode\('([^']+)'\)\)\);/", $protectedContent, $matches);
        $this->assertNotEmpty($matches[1], 'Payload should match regex');

        $originalPayload = $matches[1];
        $tamperedPayload = substr_replace($originalPayload, 'Z', 15, 1);
        $tamperedContent = str_replace($originalPayload, $tamperedPayload, $protectedContent);
        file_put_contents($targetFile, $tamperedContent);

        // Executing tampered file in a separate process should fail/exit with violation
        $phpCode = 'require_once "'.addslashes($targetFile).'";';
        $checkCmd = 'php -r '.escapeshellarg($phpCode).' 2>&1';
        exec($checkCmd, $tamperOutput, $tamperRet);

        $tamperOutputStr = implode("\n", $tamperOutput);
        $this->assertTrue(
            $tamperRet !== 0 || str_contains($tamperOutputStr, 'Bremspace Security Violation') || str_contains($tamperOutputStr, 'Tampered'),
            'Tampered bytecode must fail execution'
        );
    }

    public function test_hardware_license_signature_matches_native_specs(): void
    {
        $challenge = HardwareIdentifier::getChallengeCode();
        $this->assertMatchesRegularExpression('/^BREM-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $challenge);

        $signature = LicenseVerifier::computeSignature($challenge);
        $this->assertMatchesRegularExpression('/^ACT-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $signature);

        $this->assertTrue(LicenseVerifier::verifyKey($challenge, $signature));
        $this->assertFalse(LicenseVerifier::verifyKey($challenge, 'ACT-0000-0000-0000-0000'));
    }
}
