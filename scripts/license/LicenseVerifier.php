<?php

declare(strict_types=1);

namespace Scripts\License;

require_once __DIR__.'/HardwareIdentifier.php';

/**
 * LicenseVerifier
 *
 * Modul verifikasi offline di komputer klien:
 * - Menghasilkan Challenge Code untuk dikirim ke WhatsApp Developer
 * - Memverifikasi Activation Key (HMAC-SHA256 signature)
 * - Mengunci lisensi ke hardware mesin fisik
 */
final class LicenseVerifier
{
    /**
     * Master Verification Salt (Kunci publik verifikasi internal aplikasi)
     */
    public const PUBLIC_SALT = 'BREMSPACE_LICENSE_SECURITY_ENGINE_V1';

    /**
     * Jalur berkas lisensi di storage
     */
    public static function getLicenseFilePath(): string
    {
        $baseDir = dirname(__DIR__, 2);

        return $baseDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'license.key';
    }

    /**
     * Periksa apakah lisensi aktif dan sah di mesin ini
     */
    public static function isLicensed(): bool
    {
        $file = self::getLicenseFilePath();
        if (! file_exists($file)) {
            return false;
        }

        $content = @file_get_contents($file);
        if (! $content) {
            return false;
        }

        $data = json_decode(trim($content), true);
        if (! is_array($data) || empty($data['key']) || empty($data['challenge'])) {
            return false;
        }

        // 1. Cek kecocokan challenge dengan hardware saat ini (Anti-Duplikasi Mesin)
        $currentChallenge = HardwareIdentifier::getChallengeCode();
        if ($data['challenge'] !== $currentChallenge) {
            return false;
        }

        // 2. Verifikasi keaslian Activation Key
        return self::verifyKey($data['challenge'], $data['key']);
    }

    /**
     * Simpan lisensi baru setelah verifikasi sukses
     */
    public static function saveLicense(string $activationKey): bool
    {
        $challenge = HardwareIdentifier::getChallengeCode();

        if (! self::verifyKey($challenge, $activationKey)) {
            return false;
        }

        $payload = [
            'status' => 'ACTIVE',
            'challenge' => $challenge,
            'key' => trim($activationKey),
            'verified_at' => date('Y-m-d H:i:s'),
            'machine_hash' => HardwareIdentifier::getMachineHash(),
        ];

        $targetFile = self::getLicenseFilePath();
        $dir = dirname($targetFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return @file_put_contents($targetFile, json_encode($payload, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Algoritma Verifikasi Kriptografis Activation Key
     * Format Key: ACT-XXXX-YYYY-ZZZZ-WWWW (16 hex chars setelah ACT-)
     */
    public static function verifyKey(string $challengeCode, string $activationKey): bool
    {
        $cleanKey = strtoupper(trim($activationKey));
        if (! str_starts_with($cleanKey, 'ACT-')) {
            return false;
        }

        $expectedSignature = self::computeSignature($challengeCode);

        return hash_equals($expectedSignature, $cleanKey);
    }

    /**
     * Hitung signature deterministik berdasarkan Challenge Code
     */
    public static function computeSignature(string $challengeCode): string
    {
        $cleanChallenge = strtoupper(trim($challengeCode));
        $hash = strtoupper(hash_hmac('sha256', $cleanChallenge, self::PUBLIC_SALT));

        $p1 = substr($hash, 0, 4);
        $p2 = substr($hash, 4, 4);
        $p3 = substr($hash, 8, 4);
        $p4 = substr($hash, 12, 4);

        return "ACT-{$p1}-{$p2}-{$p3}-{$p4}";
    }
}
