<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Karyawan;
use App\Modules\Wms\Services\QrCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AbsensiQrService
{
    public const TOKEN_TTL_SECONDS = 30;

    public const CACHE_PREFIX = 'absensi_qr_';

    public const CONSUMED_PREFIX = 'absensi_qr_consumed_';

    public function __construct(
        protected QrCodeService $qrService,
        protected AbsensiService $absensiService
    ) {}

    /**
     * Generate dynamic one-time rolling QR Code untuk monitor PC toko.
     */
    public function generateRollingQr(?int $cabangId = null): array
    {
        $token = Str::random(32);
        $ttl = self::TOKEN_TTL_SECONDS;
        $expiresAt = now()->addSeconds($ttl)->timestamp;

        $meta = [
            'token' => $token,
            'cabang_id' => $cabangId,
            'created_at' => now()->timestamp,
            'expires_at' => $expiresAt,
            'used' => false,
        ];

        Cache::put(self::CACHE_PREFIX.$token, $meta, now()->addSeconds($ttl + 60));

        $url = url('/absen/scan?t='.$token);
        $svg = $this->qrService->generateSvg($url, 260, 1);

        return [
            'token' => $token,
            'url' => $url,
            'svg' => $svg,
            'expires_at' => $expiresAt,
            'ttl' => $ttl,
        ];
    }

    /**
     * Validasi dan konsumsi token QR sekali pakai (one-time consume).
     */
    public function processScan(string $token, string $employeeIdentifier, ?string $clientIp = null): array
    {
        $cleanToken = trim($token);
        $key = self::CACHE_PREFIX.$cleanToken;
        $meta = Cache::get($key);

        if (! $meta) {
            return [
                'success' => false,
                'message' => 'QR Code sudah kedaluwarsa atau tidak valid. Silakan scan QR terbaru di layar PC.',
            ];
        }

        if (($meta['used'] ?? false) || now()->timestamp > ($meta['expires_at'] ?? 0)) {
            Cache::forget($key);

            return [
                'success' => false,
                'message' => 'QR Code ini sudah pernah digunakan atau kedaluwarsa. Silakan scan QR terbaru.',
            ];
        }

        // Jalankan absensi karyawan
        $res = $this->absensiService->scanBarcode($employeeIdentifier, $meta['cabang_id'] ?? null);

        if ($res['success']) {
            // Tandai token sudah digunakan seketika agar tidak bisa dipakai ulang
            $meta['used'] = true;
            $meta['consumed_by'] = $res['karyawan']['nama'] ?? 'Karyawan';
            $meta['result'] = $res;
            Cache::put($key, $meta, 120);

            // Simpan status konsumsi untuk di-polling oleh PC Modal
            Cache::put(self::CONSUMED_PREFIX.$cleanToken, $res, 60);
        }

        return $res;
    }

    /**
     * Cek apakah token QR tertentu telah dikonsumsi oleh karyawan (untuk PC Modal auto-rotate).
     */
    public function checkConsumed(string $token): ?array
    {
        return Cache::get(self::CONSUMED_PREFIX.trim($token));
    }
}
