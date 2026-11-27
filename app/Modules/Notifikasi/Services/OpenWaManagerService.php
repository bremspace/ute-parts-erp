<?php

namespace App\Modules\Notifikasi\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backend proxy for OpenWA REST API (rmyndharis/openwa).
 *
 * All browser -> OpenWA communication goes through Laravel (auth + RBAC)
 * -> 127.0.0.1:2785. Never expose OpenWA port directly or log API keys.
 */
class OpenWaManagerService
{
    private int $timeout = 10;

    private int $connectTimeout = 5;

    /**
     * @return array{ok: bool, data: mixed, error: ?string}
     */
    public function health(string $baseUrl): array
    {
        $url = WhatsAppGatewayService::normalizeOpenWaUrl($baseUrl);

        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->get("{$url}/health/ready");

            if ($response->successful()) {
                return ['ok' => true, 'data' => $response->json(), 'error' => null];
            }

            return ['ok' => false, 'data' => null, 'error' => 'OpenWA belum siap (HTTP '.$response->status().').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => 'Tidak dapat menghubungi OpenWA: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, data: array<int, array<string, mixed>>, error: ?string}
     */
    public function listSessions(string $baseUrl, string $apiKey): array
    {
        $res = $this->request('get', $baseUrl, '/sessions', $apiKey);
        if (! $res['ok']) {
            return ['ok' => false, 'data' => [], 'error' => $res['error']];
        }

        $json = $res['data'];
        $items = [];
        if (is_array($json)) {
            $rawList = $json['data'] ?? $json['sessions'] ?? (array_is_list($json) ? $json : []);
            if (is_array($rawList)) {
                foreach ($rawList as $item) {
                    if (is_array($item)) {
                        $items[] = $this->normalizeSession($item);
                    }
                }
            }
        }

        return ['ok' => true, 'data' => $items, 'error' => null];
    }

    /**
     * @return array{ok: bool, data: ?array<string, mixed>, error: ?string}
     */
    public function createSession(string $baseUrl, string $apiKey, string $name): array
    {
        $cleanName = trim($name) ?: 'bremspace-erp';
        $res = $this->request('post', $baseUrl, '/sessions', $apiKey, ['name' => $cleanName]);
        if (! $res['ok']) {
            return $res;
        }

        $payload = is_array($res['data']) ? ($res['data']['data'] ?? $res['data']) : [];

        return [
            'ok' => true,
            'data' => is_array($payload) ? $this->normalizeSession($payload) : null,
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, data: mixed, error: ?string}
     */
    public function startSession(string $baseUrl, string $apiKey, string $sessionId): array
    {
        if (! $this->validSessionId($sessionId)) {
            return ['ok' => false, 'data' => null, 'error' => 'ID Sesi OpenWA tidak valid.'];
        }

        return $this->request('post', $baseUrl, "/sessions/{$sessionId}/start", $apiKey);
    }

    /**
     * @return array{ok: bool, data: ?array{qr: string, status: ?string}, error: ?string}
     */
    public function getQr(string $baseUrl, string $apiKey, string $sessionId): array
    {
        if (! $this->validSessionId($sessionId)) {
            return ['ok' => false, 'data' => null, 'error' => 'ID Sesi OpenWA tidak valid.'];
        }

        $res = $this->request('get', $baseUrl, "/sessions/{$sessionId}/qr", $apiKey);
        if (! $res['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $res['error']];
        }

        $json = is_array($res['data']) ? $res['data'] : [];
        $inner = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $qr = $inner['qrCode'] ?? $inner['qr'] ?? (is_string($res['data']) ? $res['data'] : '');
        $status = isset($inner['status']) ? (string) $inner['status'] : null;

        if (! is_string($qr) || $qr === '') {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'QR belum siap, pastikan sesi sedang berjalan (status qr_ready).',
            ];
        }

        return [
            'ok' => true,
            'data' => ['qr' => $qr, 'status' => $status],
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, data: ?array{code: string}, error: ?string}
     */
    public function getPairingCode(string $baseUrl, string $apiKey, string $sessionId, string $phoneNumber): array
    {
        if (! $this->validSessionId($sessionId)) {
            return ['ok' => false, 'data' => null, 'error' => 'ID Sesi OpenWA tidak valid.'];
        }

        $normalized = WhatsAppGatewayService::nomorInternasional($phoneNumber);
        if ($normalized === null) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'Format nomor WhatsApp tidak valid (gunakan 08xx atau 628xx).',
            ];
        }

        $res = $this->request('post', $baseUrl, "/sessions/{$sessionId}/pairing-code", $apiKey, [
            'phoneNumber' => $normalized,
        ]);

        if (! $res['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $res['error']];
        }

        $json = is_array($res['data']) ? $res['data'] : [];
        $inner = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $code = (string) ($inner['code'] ?? $inner['pairingCode'] ?? '');

        if ($code === '') {
            return ['ok' => false, 'data' => null, 'error' => 'Pairing code kosong dari OpenWA.'];
        }

        return ['ok' => true, 'data' => ['code' => $code], 'error' => null];
    }

    /**
     * Mint a session-scoped OPERATOR key via POST /api/auth/api-keys.
     * OpenWA CreateApiKeyDto: { name, role: "operator", allowedSessions: [sessionId] }.
     *
     * @return array{ok: bool, data: ?array{apiKey: string, id: ?string}, error: ?string}
     */
    public function createOperatorKey(string $baseUrl, string $adminKey, string $sessionId, ?string $label = null): array
    {
        if (! $this->validSessionId($sessionId)) {
            return ['ok' => false, 'data' => null, 'error' => 'ID Sesi OpenWA tidak valid.'];
        }

        $keyName = substr(trim((string) $label) ?: ('erp-operator-'.substr($sessionId, 0, 8)), 0, 100);

        $res = $this->request('post', $baseUrl, '/auth/api-keys', $adminKey, [
            'name' => $keyName,
            'role' => 'operator',
            'allowedSessions' => [$sessionId],
        ]);

        if (! $res['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $res['error']];
        }

        $json = is_array($res['data']) ? $res['data'] : [];
        $inner = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $rawKey = (string) ($inner['apiKey'] ?? $inner['key'] ?? $inner['rawKey'] ?? '');

        if ($rawKey === '') {
            return ['ok' => false, 'data' => null, 'error' => 'OpenWA tidak mengembalikan API key operator baru.'];
        }

        return [
            'ok' => true,
            'data' => [
                'apiKey' => $rawKey,
                'id' => isset($inner['id']) ? (string) $inner['id'] : null,
            ],
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, data: ?array<string, mixed>, error: ?string}
     */
    public function getSessionStatus(string $baseUrl, string $apiKey, string $sessionId): array
    {
        if (! $this->validSessionId($sessionId)) {
            return ['ok' => false, 'data' => null, 'error' => 'ID Sesi OpenWA tidak valid.'];
        }

        $res = $this->request('get', $baseUrl, "/sessions/{$sessionId}", $apiKey);
        if (! $res['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $res['error']];
        }

        $payload = is_array($res['data']) ? ($res['data']['data'] ?? $res['data']) : [];

        return [
            'ok' => true,
            'data' => is_array($payload) ? $this->normalizeSession($payload) : null,
            'error' => null,
        ];
    }

    public static function isReadyStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['ready', 'connected', 'working'], true);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeSession(array $raw): array
    {
        $status = (string) ($raw['status'] ?? 'unknown');
        $me = is_array($raw['me'] ?? null) ? $raw['me'] : [];
        $phone = $raw['phoneNumber'] ?? $raw['phone'] ?? $me['id'] ?? $me['user'] ?? null;
        if (is_string($phone) && str_contains($phone, '@')) {
            $phone = explode('@', $phone)[0];
        }

        return [
            'id' => (string) ($raw['id'] ?? $raw['sessionId'] ?? ''),
            'name' => (string) ($raw['name'] ?? ($raw['id'] ?? '')),
            'status' => $status,
            'phone' => is_string($phone) && $phone !== '' ? $phone : null,
            'is_ready' => self::isReadyStatus($status),
        ];
    }

    private function validSessionId(string $sessionId): bool
    {
        return $sessionId !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $sessionId) === 1;
    }

    /**
     * @param  'get'|'post'  $method
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, data: mixed, error: ?string}
     */
    private function request(string $method, string $baseUrl, string $path, string $apiKey, array $body = []): array
    {
        $url = WhatsAppGatewayService::normalizeOpenWaUrl($baseUrl).$path;

        try {
            $pending = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withHeaders(['X-API-Key' => $apiKey]);

            $response = $method === 'post'
                ? $pending->post($url, $body)
                : $pending->get($url);

            if ($response->successful()) {
                return ['ok' => true, 'data' => $response->json(), 'error' => null];
            }

            return $this->errorFromResponse($response);
        } catch (\Throwable $e) {
            Log::warning('OpenWaManager '.$method.' gagal', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'data' => null, 'error' => 'Tidak dapat menghubungi OpenWA: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, data: null, error: string}
     */
    private function errorFromResponse(Response $response): array
    {
        $status = $response->status();
        $json = $response->json();
        $detail = is_array($json) ? ($json['message'] ?? null) : null;
        if (is_array($detail)) {
            $detail = implode(', ', array_map('strval', $detail));
        }

        $error = match ($status) {
            400 => 'Permintaan ditolak OpenWA (HTTP 400)'.(is_string($detail) && $detail !== '' ? ': '.substr($detail, 0, 160) : '.'),
            401, 403 => 'API key ditolak (HTTP '.$status.'). Periksa Admin/Operator Key.',
            404 => 'Resource/Sesi OpenWA tidak ditemukan (HTTP 404).',
            409 => 'Sesi belum siap / belum berstatus qr_ready (HTTP 409).',
            429 => 'OpenWA rate limit (HTTP 429). Coba lagi nanti.',
            503 => 'OpenWA sedang restart / belum siap (HTTP 503).',
            default => 'OpenWA error HTTP '.$status.'.',
        };

        return ['ok' => false, 'data' => null, 'error' => $error];
    }
}
