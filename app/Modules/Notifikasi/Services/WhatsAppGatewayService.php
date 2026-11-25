<?php

namespace App\Modules\Notifikasi\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
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
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string}
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
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string}
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
            return $saved;
        }

        $env = config('services.wa', []);

        return is_array($env) ? $env : [];
    }

    /**
     * OpenWA: POST {base}/api/sessions/{session}/messages/send-text
     * Header X-API-Key, body { chatId, text }.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string}
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
            ];
        }

        $session = trim((string) ($config['session'] ?? '')) ?: 'default';
        $base = rtrim((string) $config['url'], '/');
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
            ];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'provider' => self::PROVIDER_OPENWA,
                'error' => null,
                'chat_id' => $chatId,
            ];
        }

        // Jangan sertakan body mentah (bisa memuat echo request) atau token.
        Log::warning('OpenWA menolak kiriman', [
            'session' => $session,
            'chat_id' => $chatId,
            'http' => $response->status(),
        ]);

        $pesanError = match ($response->status()) {
            401, 403 => 'OpenWA menolak API key (HTTP '.$response->status().'). Periksa API key / scope sesi.',
            404 => 'Sesi OpenWA tidak ditemukan (HTTP 404). Periksa ID sesi.',
            429 => 'OpenWA membatasi laju kirim (HTTP 429). Coba lagi nanti.',
            default => 'OpenWA error HTTP '.$response->status().'.',
        };

        return [
            'ok' => false,
            'provider' => self::PROVIDER_OPENWA,
            'error' => $pesanError,
            'chat_id' => $chatId,
        ];
    }

    /**
     * Provider gaya Fonnte/Wablas: Authorization header + {target, message}.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, provider: string, error: ?string, chat_id: ?string}
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
            ];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'provider' => (string) ($config['provider'] ?? 'custom'),
                'error' => null,
                'chat_id' => $target,
            ];
        }

        return [
            'ok' => false,
            'provider' => (string) ($config['provider'] ?? 'custom'),
            'error' => 'Gateway WA error HTTP '.$response->status().'.',
            'chat_id' => $target,
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
