<?php

namespace App\Modules\Rbac\Services;

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
}
