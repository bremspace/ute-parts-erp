<?php

declare(strict_types=1);

namespace Scripts\License;

/**
 * HardwareIdentifier
 *
 * Ekstraktor identitas hardware unik dan konsisten lintas OS:
 * - Windows: Motherboard UUID + System Drive Volume Serial
 * - Linux: /etc/machine-id + /sys/class/dmi/id/product_uuid
 * - Android (Termux): Android ID via settings get secure android_id / OS build fingerprint
 */
final class HardwareIdentifier
{
    private const SALT = 'BREMSPACE_HWID_V1_2026';

    /**
     * Dapatkan Hardware Fingerprint unik terenkripsi (Hash SHA-256)
     */
    public static function getMachineHash(): string
    {
        $rawId = self::getRawHardwareIdentifier();

        return hash('sha256', self::SALT.':'.$rawId);
    }

    /**
     * Deteksi apakah aplikasi berjalan di dalam kontainer Docker
     */
    public static function isDocker(): bool
    {
        return file_exists('/.dockerenv') ||
               file_exists('/run/.containerenv') ||
               (file_exists('/proc/1/cgroup') && str_contains((string) @file_get_contents('/proc/1/cgroup'), 'docker'));
    }

    /**
     * Dapatkan Host Challenge yang diteruskan dari OS host ke dalam container/runtime
     */
    public static function getHostChallenge(): ?string
    {
        // 1. Cek environment variables (getenv, $_ENV, $_SERVER)
        $envKeys = ['BREMSPACE_HOST_CHALLENGE'];
        foreach ($envKeys as $key) {
            $val = getenv($key);
            if (! empty($val) && is_string($val)) {
                return trim($val);
            }
            if (! empty($_ENV[$key]) && is_string($_ENV[$key])) {
                return trim($_ENV[$key]);
            }
            if (! empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                return trim($_SERVER[$key]);
            }
        }

        // 2. Cek fungsi helper env() Laravel jika tersedia
        if (function_exists('env')) {
            $envVal = env('BREMSPACE_HOST_CHALLENGE');
            if (! empty($envVal) && is_string($envVal)) {
                return trim($envVal);
            }
        }

        // 3. Cek langsung dari file .env di berbagai lokasi standar
        $envCandidate = null;
        try {
            if (function_exists('base_path') && function_exists('app') && app()->has('path.base')) {
                $envCandidate = base_path('.env');
            }
        } catch (\Throwable) {
            $envCandidate = null;
        }

        $possibleEnvPaths = array_filter([
            $envCandidate,
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env',
            '/var/www/html/.env',
        ]);
        foreach ($possibleEnvPaths as $envPath) {
            if (file_exists($envPath)) {
                $content = @file_get_contents($envPath);
                if ($content && preg_match('/^BREMSPACE_HOST_CHALLENGE=(.+)$/m', $content, $matches)) {
                    $val = trim($matches[1], " \t\n\r\0\x0B\"'");
                    if (! empty($val)) {
                        return $val;
                    }
                }
            }
        }

        // 4. Fallback dalam container Docker: Cek storage/license.key jika sudah memiliki signature yang sah
        $licenseCandidate = null;
        try {
            if (class_exists(LicenseVerifier::class)) {
                $licenseCandidate = LicenseVerifier::getLicenseFilePath();
            }
        } catch (\Throwable) {
            $licenseCandidate = null;
        }

        $possibleLicensePaths = array_filter([
            $licenseCandidate,
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'license.key',
            '/var/www/html/storage/license.key',
        ]);
        foreach ($possibleLicensePaths as $licPath) {
            if (file_exists($licPath)) {
                $licContent = @file_get_contents($licPath);
                if ($licContent) {
                    $json = json_decode(trim($licContent), true);
                    if (is_array($json) && ! empty($json['challenge']) && ! empty($json['key'])) {
                        // Pastikan key valid secara kriptografis untuk challenge tersebut
                        if (class_exists(LicenseVerifier::class) && LicenseVerifier::verifyKey((string) $json['challenge'], (string) $json['key'])) {
                            return (string) $json['challenge'];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Format Machine Challenge Code yang ramah pengguna untuk dikirim via WhatsApp
     * Format: BREM-XXXX-YYYY-ZZZZ (12 karakter alfanumerik)
     */
    public static function getChallengeCode(): string
    {
        // Jika berjalan di dalam kontainer Docker dan terdapat binding challenge host resmi
        if (self::isDocker()) {
            $hostChallenge = self::getHostChallenge();
            if (! empty($hostChallenge)) {
                return $hostChallenge;
            }
        }

        $hash = strtoupper(self::getMachineHash());
        $p1 = substr($hash, 0, 4);
        $p2 = substr($hash, 4, 4);
        $p3 = substr($hash, 8, 4);

        return "BREM-{$p1}-{$p2}-{$p3}";
    }

    /**
     * Deteksi Raw Hardware ID berdasarkan OS
     */
    public static function getRawHardwareIdentifier(): string
    {
        $os = PHP_OS_FAMILY;

        // 1. Android Termux
        if (self::isAndroid()) {
            return self::getAndroidIdentifier();
        }

        // 2. Windows
        if ($os === 'Windows') {
            return self::getWindowsIdentifier();
        }

        // 3. macOS (Darwin)
        if ($os === 'Darwin') {
            return self::getDarwinIdentifier();
        }

        // 4. Linux / BSD
        return self::getLinuxIdentifier();
    }

    private static function isAndroid(): bool
    {
        return ! empty(getenv('TERMUX_VERSION')) ||
               ! empty(getenv('PREFIX')) && str_contains((string) getenv('PREFIX'), 'com.termux') ||
               file_exists('/data/data/com.termux');
    }

    private static function getWindowsIdentifier(): string
    {
        $uuid = shell_exec('powershell -NoProfile -Command "(Get-CimInstance -ClassName Win32_ComputerSystemProduct).UUID" 2>$null');
        if (! $uuid) {
            $uuid = shell_exec('wmic csproduct get uuid 2>nul');
        }
        if ($uuid) {
            $cleaned = preg_replace('/[^A-Za-z0-9\-]/', '', $uuid);
            if (! empty($cleaned) && ! str_contains(strtolower($cleaned), 'uuid')) {
                return $cleaned;
            }
        }

        return gethostname() ?: 'WIN-GENERIC';
    }

    private static function getDarwinIdentifier(): string
    {
        // 1. Query IOPlatformUUID via ioreg
        $out = @shell_exec("ioreg -rd1 -c IOPlatformExpertDevice 2>/dev/null | awk -F'\"' '/IOPlatformUUID/{print $4}'");
        if (! empty($out)) {
            $cleaned = trim((string) preg_replace('/[^A-Za-z0-9\-]/', '', $out));
            if (! empty($cleaned)) {
                return $cleaned;
            }
        }

        // 2. Fallback via system_profiler
        $sp = @shell_exec("system_profiler SPHardwareDataType 2>/dev/null | awk -F': ' '/Hardware UUID/{print $2}'");
        if (! empty($sp)) {
            $cleaned = trim((string) preg_replace('/[^A-Za-z0-9\-]/', '', $sp));
            if (! empty($cleaned)) {
                return $cleaned;
            }
        }

        return gethostname() ?: 'MAC-GENERIC';
    }

    private static function getLinuxIdentifier(): string
    {
        // 1. /etc/machine-id (Canonical machine ID on all systemd Linux)
        if (file_exists('/etc/machine-id')) {
            $id = trim((string) @file_get_contents('/etc/machine-id'));
            if (! empty($id)) {
                return $id;
            }
        }

        // 2. /var/lib/dbus/machine-id (Fallback on D-Bus systems)
        if (file_exists('/var/lib/dbus/machine-id')) {
            $id = trim((string) @file_get_contents('/var/lib/dbus/machine-id'));
            if (! empty($id)) {
                return $id;
            }
        }

        // 3. /sys/class/dmi/id/product_uuid (DMI UUID fallback)
        if (file_exists('/sys/class/dmi/id/product_uuid')) {
            $id = trim((string) @file_get_contents('/sys/class/dmi/id/product_uuid'));
            if (! empty($id)) {
                return $id;
            }
        }

        return gethostname() ?: 'LINUX-GENERIC';
    }

    private static function getAndroidIdentifier(): string
    {
        $parts = [];

        // A. Android Secure ID via Termux command (jika diizinkan)
        $androidId = @shell_exec('settings get secure android_id 2>/dev/null');
        if ($androidId && is_string($androidId)) {
            $cleanId = trim($androidId);
            if ($cleanId !== '' && $cleanId !== 'null' && ! stripos($cleanId, 'exception') && ! stripos($cleanId, 'denied') && ! stripos($cleanId, 'permission') && strlen($cleanId) < 64) {
                $parts[] = $cleanId;
            }
        }

        // B. Getprop system fingerprint & serial (selalu terbaca oleh non-root)
        $fingerprint = @shell_exec('getprop ro.build.fingerprint 2>/dev/null');
        if ($fingerprint && is_string($fingerprint) && trim($fingerprint) !== '') {
            $parts[] = trim($fingerprint);
        } else {
            $model = trim((string) @shell_exec('getprop ro.product.model 2>/dev/null'));
            $brand = trim((string) @shell_exec('getprop ro.product.brand 2>/dev/null'));
            $device = trim((string) @shell_exec('getprop ro.product.device 2>/dev/null'));
            if ($model || $brand) {
                $parts[] = "{$brand}:{$model}:{$device}";
            }
        }

        if (empty($parts)) {
            $parts[] = 'ANDROID-'.gethostname();
        }

        return implode('|', $parts);
    }
}
