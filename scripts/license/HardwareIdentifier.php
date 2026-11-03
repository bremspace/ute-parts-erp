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
     * Format Machine Challenge Code yang ramah pengguna untuk dikirim via WhatsApp
     * Format: BREM-XXXX-YYYY-ZZZZ (12 karakter alfanumerik)
     */
    public static function getChallengeCode(): string
    {
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

        // 3. Linux / macOS
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
        $parts = [];

        // A. Motherboard / System UUID via PowerShell / WMIC
        $uuid = shell_exec('powershell -NoProfile -Command "(Get-CimInstance -ClassName Win32_ComputerSystemProduct).UUID" 2>$null');
        if (! $uuid) {
            $uuid = shell_exec('wmic csproduct get uuid 2>nul');
        }
        if ($uuid) {
            $cleaned = preg_replace('/[^A-Za-z0-9\-]/', '', $uuid);
            if (! empty($cleaned) && ! str_contains(strtolower($cleaned), 'uuid')) {
                $parts[] = $cleaned;
            }
        }

        // B. Serial Number BIOS / Motherboard
        $serial = shell_exec('powershell -NoProfile -Command "(Get-CimInstance -ClassName Win32_BIOS).SerialNumber" 2>$null');
        if ($serial) {
            $cleaned = preg_replace('/[^A-Za-z0-9\-]/', '', $serial);
            if (! empty($cleaned)) {
                $parts[] = $cleaned;
            }
        }

        // C. System Drive Volume Serial Number
        $vol = shell_exec('vol C: 2>nul');
        if ($vol && preg_match('/([0-9A-Fa-f]{4}-[0-9A-Fa-f]{4})/', $vol, $m)) {
            $parts[] = $m[1];
        }

        if (empty($parts)) {
            // Fallback: CPU Processor ID / Machine Name
            $parts[] = gethostname() ?: 'WIN-GENERIC';
        }

        return implode('|', $parts);
    }

    private static function getLinuxIdentifier(): string
    {
        $parts = [];

        // A. /etc/machine-id atau /var/lib/dbus/machine-id
        if (file_exists('/etc/machine-id')) {
            $id = trim((string) @file_get_contents('/etc/machine-id'));
            if (! empty($id)) {
                $parts[] = $id;
            }
        } elseif (file_exists('/var/lib/dbus/machine-id')) {
            $id = trim((string) @file_get_contents('/var/lib/dbus/machine-id'));
            if (! empty($id)) {
                $parts[] = $id;
            }
        }

        // B. DMI Product UUID (Jika ada hak baca / VPS)
        if (file_exists('/sys/class/dmi/id/product_uuid')) {
            $uuid = trim((string) @file_get_contents('/sys/class/dmi/id/product_uuid'));
            if (! empty($uuid)) {
                $parts[] = $uuid;
            }
        }

        // C. Root Disk UUID via blkid / lsblk
        $diskUuid = shell_exec('lsblk -no UUID $(df / | tail -n 1 | awk \'{print $1}\') 2>/dev/null');
        if ($diskUuid && trim($diskUuid) !== '') {
            $parts[] = trim($diskUuid);
        }

        if (empty($parts)) {
            $parts[] = gethostname() ?: 'LINUX-GENERIC';
        }

        return implode('|', $parts);
    }

    private static function getAndroidIdentifier(): string
    {
        $parts = [];

        // A. Android Secure ID via Termux command
        $androidId = shell_exec('settings get secure android_id 2>/dev/null');
        if ($androidId && trim($androidId) !== '' && trim($androidId) !== 'null') {
            $parts[] = trim($androidId);
        }

        // B. Getprop system fingerprint / serial
        $fingerprint = shell_exec('getprop ro.build.fingerprint 2>/dev/null');
        if ($fingerprint && trim($fingerprint) !== '') {
            $parts[] = trim($fingerprint);
        }

        if (empty($parts)) {
            $parts[] = 'ANDROID-'.gethostname();
        }

        return implode('|', $parts);
    }
}
