<?php

namespace App\Modules\Notifikasi\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Satu pintu untuk pengiriman WhatsApp keluar (Fonnte/Wablas/Custom/OpenWA).
 *
 * Aman:
 * - Kredensial tidak pernah dibuang ke pesan error/log (dimask).
 * - Timeout + connect timeout; retry diatur queue (bukan sync di request).
 * - Provider OpenWA hanya aktif bila dikonfigurasi; default tetap behaviour
 *   Fonnte/Wablas yang sudah ada.
 */
class WhatsAppGatewayService
{
    public const PROVIDER_OPENWA = 'openwa';

    public function __construct(
        private readonly KonfigurasiService $konfigurasi,
    ) {}

    /**
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string, retryable: bool}
     */
    public function kirim(?string $tujuan, string $pesan): array
    {
        return $this->kirimDenganConfig($this->config(), $tujuan, $pesan);
    }

    /**
     * Kirim dengan konfigurasi eksplisit (dipakai tes kirim dari UI Pengaturan,
     * agar tidak perlu menyimpan dulu ke tabel konfigurasi).
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string, retryable: bool}
     */
    public function kirimDenganConfig(array $config, ?string $tujuan, string $pesan): array
    {
        $provider = (string) ($config['provider'] ?? 'custom');

        if (blank($config['url'] ?? null) || blank($config['token'] ?? null)) {
            return [
                'ok' => false,
                'provider' => $provider,
                'error' => 'gateway WA belum dikonfigurasi. Atur kredensial gateway WA sebelum mengirim.',
                'chat_id' => null,
                'retryable' => false,
            ];
        }

        return $provider === self::PROVIDER_OPENWA
            ? $this->kirimOpenWa($config, $tujuan, $pesan)
            : $this->kirimGeneric($config, $tujuan, $pesan);
    }

    /**
     * Konfigurasi WA: prioritaskan yang disimpan lewat UI Pengaturan (tabel
     * konfigurasi), fallback ke config('services.wa') dari .env.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $saved = $this->konfigurasi->get('wa_gateway_config');

        if (is_array($saved) && filled($saved['url'] ?? null) && filled($saved['token'] ?? null)) {
            // Decrypt token if encrypted
            if (filled($saved['token'])) {
                try {
                    $saved['token'] = Crypt::decryptString($saved['token']);
                } catch (DecryptException) {
                    // Legacy plaintext — use as-is
                }
            }

            return $saved;
        }

        // Fallback: check OpenWA .env config first, then generic WA
        $openwa = config('services.openwa', []);
        if (is_array($openwa) && filled($openwa['api_key'] ?? null)) {
            return [
                'provider' => self::PROVIDER_OPENWA,
                'url' => $openwa['url'] ?? 'http://127.0.0.1:2785/api',
                'token' => $openwa['api_key'],
                'session' => $openwa['session'] ?? '',
            ];
        }

        $env = config('services.wa', []);

        return is_array($env) ? $env : [];
    }

    /**
     * OpenWA: POST {base}/api/sessions/{session}/messages/send-text
     * Header X-API-Key, body { chatId, text }.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string, retryable: bool}
     */
    protected function kirimOpenWa(array $config, ?string $tujuan, string $pesan): array
    {
        $chatId = self::chatId($tujuan);

        if ($chatId === null) {
            return [
                'ok' => false,
                'provider' => self::PROVIDER_OPENWA,
                'error' => 'Format nomor WhatsApp tujuan tidak valid.',
                'chat_id' => null,
                'retryable' => false,
            ];
        }

        $session = trim((string) ($config['session'] ?? ''));
        if ($session === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $session) !== 1) {
            return [
                'ok' => false,
                'provider' => self::PROVIDER_OPENWA,
                'error' => $session === ''
                    ? 'ID Sesi OpenWA belum diisi. Atur lewat Pengaturan → Integrasi.'
                    : 'Format ID Sesi OpenWA tidak valid.',
                'chat_id' => $chatId,
                'retryable' => false,
            ];
        }

        $base = self::normalizeOpenWaUrl((string) $config['url']);
        $timeout = (int) ($config['timeout'] ?? config('services.openwa.timeout', 20));

        try {
            $response = Http::connectTimeout(5)
                ->timeout(max(5, $timeout))
                ->withHeaders(['X-API-Key' => (string) $config['token']])
                ->post("{$base}/sessions/{$session}/messages/send-text", [
                    'chatId' => $chatId,
                    'text' => $pesan,
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenWA unreachable', [
                'session' => $session,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'provider' => self::PROVIDER_OPENWA,
                'error' => 'Gateway OpenWA tidak dapat dihubungi: '.$e->getMessage(),
                'chat_id' => $chatId,
                'retryable' => true,
            ];
        }

        if ($response->successful()) {
            $json = $response->json();

            // Portal/login HTML sering balas HTTP 200. Jangan anggap itu sukses.
            if (! is_array($json)) {
                Log::warning('OpenWA mengembalikan non-JSON', [
                    'session' => $session,
                    'chat_id' => $chatId,
                    'http' => $response->status(),
                    'content_type' => $response->header('Content-Type'),
                ]);

                return [
                    'ok' => false,
                    'provider' => self::PROVIDER_OPENWA,
                    'error' => 'OpenWA mengembalikan HTML/bukan JSON (kemungkinan halaman login). Periksa URL & port API OpenWA.',
                    'chat_id' => $chatId,
                ];
            }

            if (array_key_exists('success', $json) && ! $json['success']) {
                return [
                    'ok' => false,
                    'provider' => self::PROVIDER_OPENWA,
                    'error' => 'OpenWA menolak pengiriman: '.(string) ($json['message'] ?? $json['error'] ?? 'unknown error'),
                    'chat_id' => $chatId,
                ];
            }

            return [
                'ok' => true,
                'provider' => self::PROVIDER_OPENWA,
                'error' => null,
                'chat_id' => $chatId,
                'retryable' => false,
            ];
        }

        // Jangan sertakan body mentah (bisa memuat echo request) atau token.
        Log::warning('OpenWA menolak kiriman', [
            'session' => $session,
            'chat_id' => $chatId,
            'http' => $response->status(),
        ]);

        $status = $response->status();
        $pesanError = match ($status) {
            401, 403 => 'OpenWA menolak API key (HTTP '.$status.'). Periksa API key / scope sesi.',
            404 => 'Sesi OpenWA tidak ditemukan (HTTP 404). Periksa ID sesi (UUID) di Pengaturan → Integrasi.',
            409 => 'Sesi OpenWA belum ready (HTTP 409). Scan QR dulu lewat Pengaturan → Integrasi.',
            429 => 'OpenWA membatasi laju kirim (HTTP 429). Coba lagi nanti.',
            503 => 'OpenWA sedang restart / tidak siap (HTTP 503). Coba lagi nanti.',
            default => 'OpenWA error HTTP '.$status.'.',
        };

        return [
            'ok' => false,
            'provider' => self::PROVIDER_OPENWA,
            'error' => $pesanError,
            'chat_id' => $chatId,
            'retryable' => in_array($status, [409, 429, 502, 503, 504], true),
        ];
    }

    /**
     * Provider gaya Fonnte/Wablas: Authorization header + {target, message}.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string, retryable: bool}
     */
    protected function kirimGeneric(array $config, ?string $tujuan, string $pesan): array
    {
        $target = self::nomorInternasional($tujuan);

        if ($target === null) {
            return [
                'ok' => false,
                'provider' => (string) ($config['provider'] ?? 'custom'),
                'error' => 'Format nomor WhatsApp tujuan tidak valid.',
                'chat_id' => null,
                'retryable' => false,
            ];
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withHeaders(['Authorization' => (string) $config['token']])
                ->post((string) $config['url'], [
                    'target' => $target,
                    'message' => $pesan,
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'provider' => (string) ($config['provider'] ?? 'custom'),
                'error' => 'Gateway WA tidak dapat dihubungi: '.$e->getMessage(),
                'chat_id' => $target,
                'retryable' => true,
            ];
        }

        if ($response->successful()) {
            $json = $response->json();
            if (is_array($json) && array_key_exists('success', $json) && ! $json['success']) {
                return [
                    'ok' => false,
                    'provider' => (string) ($config['provider'] ?? 'custom'),
                    'error' => 'Gateway menolak pengiriman: '.(string) ($json['message'] ?? $json['error'] ?? 'unknown error'),
                    'chat_id' => $target,
                ];
            }

            return [
                'ok' => true,
                'provider' => (string) ($config['provider'] ?? 'custom'),
                'error' => null,
                'chat_id' => $target,
                'retryable' => false,
            ];
        }

        $status = $response->status();

        return [
            'ok' => false,
            'provider' => (string) ($config['provider'] ?? 'custom'),
            'error' => 'Gateway WA error HTTP '.$status.'.',
            'chat_id' => $target,
            'retryable' => in_array($status, [429, 502, 503, 504], true),
        ];
    }

    /**
     * Normalisasi berbagai format HP Indonesia ke chatId WhatsApp (`628…@c.us`).
     */
    public static function chatId(?string $telepon): ?string
    {
        $nomor = self::nomorInternasional($telepon);

        return $nomor === null ? null : $nomor.'@c.us';
    }

    /**
     * 08… / 8… / 628… / +62 8… → 628… (tanpa @ dan tanda baca).
     */
    public static function nomorInternasional(?string $telepon): ?string
    {
        $kanonik = app(PelangganService::class)->normalizePhone($telepon);

        if ($kanonik === '' || ! str_starts_with($kanonik, '62')) {
            return null;
        }

        return $kanonik;
    }

    /**
     * Normalize OpenWA base URL: strip trailing slashes, ensure ends with /api.
     */
    public static function normalizeOpenWaUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('#/api$#i', '', $url) ?? $url;

        return $url.'/api';
    }

    /**
     * Mask kredensial untuk ditampilkan/dilog (jangan pernah bocor utuh).
     */
    public static function maskKredensial(?string $nilai): string
    {
        $nilai = (string) $nilai;

        if ($nilai === '') {
            return '(kosong)';
        }

        if (strlen($nilai) <= 8) {
            return '***';
        }

        return substr($nilai, 0, 4).'…'.substr($nilai, -4);
    }
}
