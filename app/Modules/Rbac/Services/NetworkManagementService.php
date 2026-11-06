<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Wms\Services\QrCodeService;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Throwable;

class NetworkManagementService
{
    public function __construct(
        protected AppUpdateService $updateService
    ) {}

    /**
     * Dapatkan status jaringan server lokal saat ini.
     */
    public function getStatus(): array
    {
        $appUrl = (app()->bound('config') ? (string) config('app.url') : null) ?: (string) env('APP_URL', 'http://localhost:1741');
        $networkMode = (string) env('NETWORK_MODE', $this->inferModeFromUrl($appUrl));
        $lanIp = $this->detectLanIp();
        $cloudflaredInfo = $this->checkCloudflared();
        $tunnelToken = (string) env('CLOUDFLARE_TUNNEL_TOKEN', '');

        // Parse host/domain dari APP_URL
        $parsed = parse_url($appUrl);
        $currentDomain = $parsed['host'] ?? 'localhost';
        if (isset($parsed['port']) && ! in_array((int) $parsed['port'], [80, 443], true)) {
            $currentDomain .= ':'.$parsed['port'];
        }

        $isOnline = $networkMode === 'online' || (! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1'));

        return [
            'network_mode' => $isOnline ? 'online' : 'lan',
            'app_url' => $appUrl,
            'current_domain' => $currentDomain,
            'is_online' => $isOnline,
            'lan_ip' => $lanIp,
            'server_port' => $parsed['port'] ?? 1741,
            'cloudflared_installed' => $cloudflaredInfo['installed'],
            'cloudflared_running' => $cloudflaredInfo['running'],
            'cloudflared_path' => $cloudflaredInfo['path'],
            'cloudflared_version' => $cloudflaredInfo['version'],
            'tunnel_token' => $tunnelToken,
            'tunnel_token_masked' => ! empty($tunnelToken) ? (substr($tunnelToken, 0, 10).'...'.substr($tunnelToken, -6)) : '',
        ];
    }

    /**
     * Upgrade server lokal ke mode Full Online dengan domain sendiri.
     */
    public function upgradeToOnline(string $domain, string $method = 'cloudflare', ?string $tunnelToken = null): array
    {
        // Sanitasi domain
        $cleanDomain = preg_replace('#^https?://#', '', trim($domain));
        $cleanDomain = rtrim($cleanDomain, '/');

        // Validasi format domain/subdomain
        if (empty($cleanDomain) || ! preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(:\d+)?$/', $cleanDomain)) {
            return [
                'success' => false,
                'message' => 'Format domain tidak valid. Contoh yang benar: pos.tokoberkah.com atau tokosaya.id',
            ];
        }

        $newAppUrl = 'https://'.$cleanDomain;

        $envUpdates = [
            'APP_URL' => $newAppUrl,
            'NETWORK_MODE' => 'online',
        ];

        if ($method === 'cloudflare' && ! empty($tunnelToken)) {
            $envUpdates['CLOUDFLARE_TUNNEL_TOKEN'] = trim($tunnelToken);
        }

        $saved = $this->updateService->updateEnv($envUpdates);
        if (! $saved) {
            return [
                'success' => false,
                'message' => 'Gagal memperbarui berkas .env. Periksa izin akses file server.',
            ];
        }

        $tunnelMessage = '';
        if ($method === 'cloudflare' && ! empty($tunnelToken)) {
            $cf = $this->checkCloudflared();
            if ($cf['installed']) {
                $tunnelMessage = $this->applyCloudflaredService(trim($tunnelToken));
            } else {
                $tunnelMessage = 'Token disimpan di .env. Anda dapat mengaktifkan service cloudflared di terminal.';
            }
        }

        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
        }

        return [
            'success' => true,
            'message' => 'Server berhasil diupgrade ke Mode Online! Domain aktif: '.$newAppUrl.($tunnelMessage ? ' ('.$tunnelMessage.')' : ''),
            'app_url' => $newAppUrl,
        ];
    }

    /**
     * Kembalikan server ke mode LAN (Offline lokal).
     */
    public function revertToLan(?string $customLanUrl = null): array
    {
        $lanIp = $this->detectLanIp();
        $targetUrl = $customLanUrl ?: ($lanIp ? 'http://'.$lanIp.':1741' : 'http://localhost:1741');

        $saved = $this->updateService->updateEnv([
            'APP_URL' => $targetUrl,
            'NETWORK_MODE' => 'lan',
        ]);

        if (! $saved) {
            return [
                'success' => false,
                'message' => 'Gagal memperbarui berkas .env.',
            ];
        }

        // Hentikan service cloudflared jika berjalan
        $this->stopCloudflaredService();

        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
        }

        return [
            'success' => true,
            'message' => 'Server berhasil dikembalikan ke Mode Jaringan Lokal (LAN). URL: '.$targetUrl,
            'app_url' => $targetUrl,
        ];
    }

    /**
     * Dapatkan rincian URL & QR Code untuk akses multi-perangkat (HP/Tablet di LAN & Online).
     *
     * @return array{
     *     lan_ip: string,
     *     port: int,
     *     lan_url: string,
     *     lan_qr_svg: string,
     *     is_online: bool,
     *     online_url: ?string,
     *     online_qr_svg: ?string,
     *     recommended_url: string,
     *     recommended_qr_svg: string
     * }
     */
    public function getDeviceAccessUrls(): array
    {
        $status = $this->getStatus();
        $lanIp = $status['lan_ip'] ?: '127.0.0.1';
        $port = (int) ($status['server_port'] ?: 1741);
        $isStandardPort = in_array($port, [80, 443], true);

        $lanUrl = 'http://'.$lanIp.($isStandardPort ? '' : ':'.$port).'/app';
        $onlineUrl = $status['is_online'] ? rtrim($status['app_url'], '/').'/app' : null;

        $recommendedUrl = $status['is_online'] && $onlineUrl ? $onlineUrl : $lanUrl;

        $qrService = (function_exists('app') && app()->bound(QrCodeService::class))
            ? app(QrCodeService::class)
            : new QrCodeService;
        $lanQrSvg = $qrService->generateSvg($lanUrl, 160, 0);
        $onlineQrSvg = $onlineUrl ? $qrService->generateSvg($onlineUrl, 160, 0) : null;
        $recommendedQrSvg = $status['is_online'] && $onlineQrSvg ? $onlineQrSvg : $lanQrSvg;

        return [
            'lan_ip' => $lanIp,
            'port' => $port,
            'lan_url' => $lanUrl,
            'lan_qr_svg' => $lanQrSvg,
            'is_online' => $status['is_online'],
            'online_url' => $onlineUrl,
            'online_qr_svg' => $onlineQrSvg,
            'recommended_url' => $recommendedUrl,
            'recommended_qr_svg' => $recommendedQrSvg,
        ];
    }

    /**
     * Deteksi IP jaringan lokal (LAN / Wi-Fi).
     */
    public function detectLanIp(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $proc = Process::fromShellCommandline('ipconfig');
                $proc->run();
                if ($proc->isSuccessful() && preg_match('/IPv4 Address[ .:]+([0-9.]+)/', $proc->getOutput(), $m)) {
                    return $m[1];
                }
            } catch (Throwable) {
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            try {
                $proc = Process::fromShellCommandline('ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null');
                $proc->run();
                $ip = trim($proc->getOutput());
                if (! empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } catch (Throwable) {
            }
        } else {
            try {
                $proc = Process::fromShellCommandline("hostname -I 2>/dev/null | awk '{print $1}'");
                $proc->run();
                $ip = trim($proc->getOutput());
                if (! empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } catch (Throwable) {
            }
        }

        // Fallback: cek hostname PHP atau server addr
        try {
            $hostIp = @gethostbyname(@gethostname());
            if (! empty($hostIp) && $hostIp !== gethostname() && filter_var($hostIp, FILTER_VALIDATE_IP) && ! str_starts_with($hostIp, '127.')) {
                return $hostIp;
            }
        } catch (Throwable) {
        }

        return (string) ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
    }

    /**
     * Periksa status binary dan service cloudflared.
     */
    public function checkCloudflared(): array
    {
        $installed = false;
        $running = false;
        $path = '';
        $version = '';

        try {
            $cmd = PHP_OS_FAMILY === 'Windows' ? 'where cloudflared' : 'which cloudflared';
            $proc = Process::fromShellCommandline($cmd);
            $proc->run();
            if ($proc->isSuccessful() && ! empty(trim($proc->getOutput()))) {
                $path = trim(explode("\n", $proc->getOutput())[0]);
                $installed = file_exists($path);

                if ($installed) {
                    $vProc = Process::fromShellCommandline("{$path} --version");
                    $vProc->run();
                    if ($vProc->isSuccessful()) {
                        $version = trim($vProc->getOutput());
                    }

                    // Cek apakah proses cloudflared sedang berjalan
                    $pCmd = PHP_OS_FAMILY === 'Windows'
                        ? 'tasklist | findstr /i cloudflared'
                        : 'pgrep -x cloudflared || systemctl is-active cloudflared 2>/dev/null';
                    $pProc = Process::fromShellCommandline($pCmd);
                    $pProc->run();
                    $running = $pProc->isSuccessful() && ! empty(trim($pProc->getOutput()));
                }
            }
        } catch (Throwable) {
        }

        return [
            'installed' => $installed,
            'running' => $running,
            'path' => $path,
            'version' => $version,
        ];
    }

    /**
     * Terapkan service cloudflared jika izin sistem mencukupi.
     */
    protected function applyCloudflaredService(string $token): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            try {
                // Jika memiliki hak sudo / root
                $proc = Process::fromShellCommandline("sudo cloudflared service install {$token} 2>/dev/null || cloudflared service install {$token} 2>/dev/null");
                $proc->run();
                if ($proc->isSuccessful()) {
                    $startProc = Process::fromShellCommandline('sudo systemctl start cloudflared 2>/dev/null || systemctl start cloudflared 2>/dev/null');
                    $startProc->run();

                    return 'Service Cloudflare Tunnel berhasil dipasang & dinyalakan';
                }
            } catch (Throwable) {
            }
        }

        return 'Token tersimpan di .env';
    }

    /**
     * Hentikan service cloudflared jika ada.
     */
    protected function stopCloudflaredService(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            try {
                $proc = Process::fromShellCommandline('sudo systemctl stop cloudflared 2>/dev/null || systemctl stop cloudflared 2>/dev/null');
                $proc->run();
            } catch (Throwable) {
            }
        }
    }

    /**
     * Infer mode jaringan dari URL aplikasi.
     */
    protected function inferModeFromUrl(string $url): string
    {
        if (str_starts_with($url, 'https://') && ! str_contains($url, 'localhost') && ! str_contains($url, '127.0.0.1')) {
            return 'online';
        }

        return 'lan';
    }
}
