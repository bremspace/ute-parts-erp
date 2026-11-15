<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Marketplace\Models\PelangganDeviceSession;
use App\Modules\Rbac\Models\DeviceSession;
use Illuminate\Support\Collection;

/**
 * [F3-4 / G-15] Session Management — device list, force logout, timeout.
 */
class SessionManagementService
{
    /**
     * Daftar device aktif user.
     *
     * @return Collection<int, DeviceSession>
     */
    public function getDeviceList(int $userId): Collection
    {
        return DeviceSession::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Force logout device tertentu.
     */
    public function forceLogoutDevice(string $deviceToken, ?int $userId = null): bool
    {
        $query = DeviceSession::where('device_token', $deviceToken)
            ->where('is_active', true);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $device = $query->first();

        if ($device) {
            $device->update(['is_active' => false]);

            return true;
        }

        return false;
    }

    /**
     * Force logout semua device user.
     */
    public function forceLogoutAll(int $userId): int
    {
        return DeviceSession::where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Cleanup session expired.
     */
    public function cleanupExpired(int $timeoutMinutes = 30): int
    {
        $threshold = now()->subMinutes($timeoutMinutes);

        return DeviceSession::where('is_active', true)
            ->where('last_activity', '<', $threshold)
            ->update(['is_active' => false]);
    }

    /**
     * Register device session baru (atau update existing).
     */
    public function registerDevice(int $userId, string $deviceName, string $deviceToken, ?string $ipAddress = null, ?string $userAgent = null): DeviceSession
    {
        $existing = DeviceSession::where('device_token', $deviceToken)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->update([
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_activity' => now(),
                'expires_at' => now()->addMinutes(30),
                'is_active' => true,
            ]);

            return $existing->fresh();
        }

        return DeviceSession::create([
            'user_id' => $userId,
            'device_name' => $deviceName,
            'device_token' => $deviceToken,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(30),
            'is_active' => true,
        ]);
    }

    // ================= SESI MEMBER (guard customer) =================

    /**
     * Daftar device aktif milik member.
     *
     * @return Collection<int, PelangganDeviceSession>
     */
    public function getPelangganDeviceList(int $pelangganId): Collection
    {
        return PelangganDeviceSession::where('pelanggan_id', $pelangganId)
            ->where('is_active', true)
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Register device session member (atau update existing).
     */
    public function registerPelangganDevice(int $pelangganId, string $deviceName, string $deviceToken, ?string $ipAddress = null, ?string $userAgent = null): PelangganDeviceSession
    {
        $existing = PelangganDeviceSession::where('device_token', $deviceToken)
            ->where('pelanggan_id', $pelangganId)
            ->first();

        if ($existing) {
            $existing->update([
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_activity' => now(),
                'expires_at' => now()->addMinutes(120),
                'is_active' => true,
            ]);

            return $existing->fresh();
        }

        return PelangganDeviceSession::create([
            'pelanggan_id' => $pelangganId,
            'device_name' => $deviceName,
            'device_token' => $deviceToken,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(120),
            'is_active' => true,
        ]);
    }

    /**
     * Cek apakah sesi perangkat member masih berlaku.
     */
    public function pelangganDeviceAktif(int $pelangganId, string $deviceToken): bool
    {
        if ($deviceToken === '') {
            return false;
        }

        return PelangganDeviceSession::where('pelanggan_id', $pelangganId)
            ->where('device_token', $deviceToken)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Perbarui last_activity bila sudah >2 menit (hindari write tiap request).
     */
    public function sentuhPelangganDevice(int $pelangganId, string $deviceToken): void
    {
        if ($deviceToken === '') {
            return;
        }

        PelangganDeviceSession::where('pelanggan_id', $pelangganId)
            ->where('device_token', $deviceToken)
            ->where('is_active', true)
            ->where('last_activity', '<', now()->subMinutes(2))
            ->update(['last_activity' => now()]);
    }

    /**
     * Force logout device member tertentu (hanya milik member tsb).
     */
    public function forceLogoutPelangganDevice(string $deviceToken, ?int $pelangganId = null): bool
    {
        $query = PelangganDeviceSession::where('device_token', $deviceToken)
            ->where('is_active', true);

        if ($pelangganId) {
            $query->where('pelanggan_id', $pelangganId);
        }

        $device = $query->first();

        if ($device) {
            $device->update(['is_active' => false]);

            return true;
        }

        return false;
    }

    /**
     * Force logout semua device member (kecuali token saat ini bila diberikan).
     */
    public function forceLogoutAllPelanggan(int $pelangganId, ?string $exceptToken = null): int
    {
        $query = PelangganDeviceSession::where('pelanggan_id', $pelangganId)
            ->where('is_active', true);

        if ($exceptToken) {
            $query->where('device_token', '!=', $exceptToken);
        }

        return $query->update(['is_active' => false]);
    }

    /**
     * Bersihkan sesi perangkat member yang sudah melewati batas waktu idle.
     * (Sesi driver `database` punya SESSION_LIFETIME 120 menit.)
     */
    public function cleanupExpiredPelanggan(int $timeoutMinutes = 120): int
    {
        $threshold = now()->subMinutes($timeoutMinutes);

        return PelangganDeviceSession::where('is_active', true)
            ->where('last_activity', '<', $threshold)
            ->update(['is_active' => false]);
    }
}
