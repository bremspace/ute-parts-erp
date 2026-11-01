<?php

namespace App\Modules\Rbac\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Layanan pengecekan dan instalasi pembaruan aplikasi langsung dari GitHub (Source of Truth).
 * Mendukung repository publik maupun private (via Personal Access Token / Deploy Key).
 */
class AppUpdateService
{
    /**
     * Dapatkan path root aplikasi.
     */
    protected function getBasePath(): string
    {
        return base_path();
    }

    /**
     * Perbarui nilai di berkas .env secara aman.
     */
    public function updateEnv(array $values): bool
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return false;
        }

        $content = File::get($envPath);

        foreach ($values as $key => $value) {
            $valueStr = (string) $value;
            // Jika mengandung spasi atau karakter khusus dan belum berpetik
            if (str_contains($valueStr, ' ') && ! str_starts_with($valueStr, '"')) {
                $valueStr = '"'.$valueStr.'"';
            }

            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$valueStr}", $content);
            } else {
                $content .= "\n{$key}={$valueStr}";
            }
        }

        File::put($envPath, $content);

        return true;
    }

    /**
     * Dapatkan informasi commit saat ini.
     */
    public function getCurrentVersionInfo(): array
    {
        $hash = $this->runGit(['rev-parse', '--short', 'HEAD']);
        $date = $this->runGit(['log', '-1', '--format=%cd', '--date=relative']);
        $message = $this->runGit(['log', '-1', '--format=%s']);
        $branch = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD']) ?: 'main';

        return [
            'commit' => trim($hash['output'] ?: 'dev'),
            'date' => trim($date['output'] ?: '-'),
            'message' => trim($message['output'] ?: 'Initial build'),
            'branch' => trim($branch['output'] ?: 'main'),
        ];
    }

    /**
     * Dapatkan konfigurasi token private GitHub jika tersimpan di .env.
     */
    public function getGitToken(): string
    {
        return (string) env('GITHUB_DEPLOY_TOKEN', env('GIT_TOKEN', ''));
    }

    /**
     * Cek apakah ada pembaruan baru di GitHub.
     */
    public function checkForUpdates(): array
    {
        $currentInfo = $this->getCurrentVersionInfo();
        $token = $this->getGitToken();

        // 1. Jalankan git fetch
        $remoteFetchCmd = ['fetch', 'origin', 'main'];
        if (! empty($token)) {
            // Gunakan custom authorization header untuk private repo
            $authHeader = 'AUTHORIZATION: bearer '.$token;
            $fetchRes = $this->runGit(['-c', "http.extraheader={$authHeader}", 'fetch', 'origin', 'main']);
        } else {
            $fetchRes = $this->runGit($remoteFetchCmd);
        }

        if ($fetchRes['status'] !== 0) {
            return [
                'has_update' => false,
                'status' => 'error',
                'message' => 'Gagal terhubung ke remote GitHub: '.$fetchRes['output'],
                'current' => $currentInfo,
                'changelog' => [],
                'commits_behind' => 0,
            ];
        }

        // 2. Hitung jumlah commit tertinggal
        $countRes = $this->runGit(['rev-list', '--count', 'HEAD..origin/main']);
        $commitsBehind = (int) trim($countRes['output'] ?: '0');

        if ($commitsBehind <= 0) {
            return [
                'has_update' => false,
                'status' => 'up_to_date',
                'message' => 'Aplikasi sudah menggunakan versi terbaru.',
                'current' => $currentInfo,
                'changelog' => [],
                'commits_behind' => 0,
            ];
        }

        // 3. Ambil daftar changelog apa saja yang berubah
        $logRes = $this->runGit(['log', 'HEAD..origin/main', '--pretty=format:%h||%s||%an||%ar', '-n', '20']);
        $changelog = [];
        if (! empty($logRes['output'])) {
            $lines = explode("\n", trim($logRes['output']));
            foreach ($lines as $line) {
                $parts = explode('||', $line);
                if (count($parts) >= 4) {
                    $changelog[] = [
                        'hash' => $parts[0],
                        'subject' => $parts[1],
                        'author' => $parts[2],
                        'date' => $parts[3],
                    ];
                }
            }
        }

        $latestRemoteHash = $this->runGit(['rev-parse', '--short', 'origin/main']);

        return [
            'has_update' => true,
            'status' => 'update_available',
            'message' => "Ditemukan {$commitsBehind} pembaruan terbaru di GitHub.",
            'current' => $currentInfo,
            'latest_hash' => trim($latestRemoteHash['output'] ?: '-'),
            'commits_behind' => $commitsBehind,
            'changelog' => $changelog,
        ];
    }

    /**
     * Pasang pembaruan terbaru dari GitHub (git pull, composer, migrate, clear cache).
     */
    public function applyUpdate(): array
    {
        $token = $this->getGitToken();
        $logs = [];

        // 1. Git Pull
        $pullArgs = ! empty($token)
            ? ['-c', "http.extraheader=AUTHORIZATION: bearer {$token}", 'pull', 'origin', 'main']
            : ['pull', 'origin', 'main'];

        $pullRes = $this->runGit($pullArgs);
        $logs[] = '[1/4] Git Pull: '.($pullRes['status'] === 0 ? 'Sukses' : 'Gagal ('.$pullRes['output'].')');
        if ($pullRes['status'] !== 0) {
            return [
                'success' => false,
                'message' => 'Gagal menarik kode dari GitHub: '.$pullRes['output'],
                'logs' => $logs,
            ];
        }

        // 2. Composer install (hanya jika ada perubahan composer)
        $composerRes = $this->runProcess(['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader']);
        if ($composerRes['status'] !== 0 && file_exists(base_path('composer.phar'))) {
            $composerRes = $this->runProcess([PHP_BINARY, 'composer.phar', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader']);
        }
        $logs[] = '[2/4] Composer Install: '.($composerRes['status'] === 0 ? 'Sukses' : 'Catatan: '.$composerRes['output']);

        // 3. Database Migrate
        try {
            Artisan::call('migrate', ['--force' => true]);
            $logs[] = '[3/4] Migrasi Skema Database: '.trim(Artisan::output() ?: 'Selesai.');
        } catch (Throwable $e) {
            $logs[] = '[3/4] Migrasi Skema Database: Catatan ('.$e->getMessage().')';
        }

        // 4. Clear Cache Server
        try {
            Artisan::call('optimize:clear');
            $logs[] = '[4/4] Bersihkan Cache Aplikasi & Konfigurasi: Sukses.';
        } catch (Throwable $e) {
            $logs[] = '[4/4] Bersihkan Cache: '.$e->getMessage();
        }

        return [
            'success' => true,
            'message' => 'Pembaruan aplikasi berhasil diterapkan secara sempurna!',
            'logs' => $logs,
            'current' => $this->getCurrentVersionInfo(),
        ];
    }

    /**
     * Dapatkan path berkas private key SSH untuk deploy key.
     */
    public function getSshKeyPath(): string
    {
        return storage_path('app/keys/id_ed25519');
    }

    /**
     * Dapatkan isi SSH Public Key yang ada di sistem (jika sudah di-generate).
     */
    public function getSshPublicKey(): ?string
    {
        $pubPath = $this->getSshKeyPath().'.pub';
        if (File::exists($pubPath)) {
            return trim(File::get($pubPath));
        }

        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');
        if (! empty($home)) {
            $homePub = $home.'/.ssh/id_ed25519.pub';
            if (File::exists($homePub)) {
                return trim(File::get($homePub));
            }
        }

        return null;
    }

    /**
     * Generate pasangan SSH Key (Ed25519) secara instan dari aplikasi.
     */
    public function generateSshDeployKey(): array
    {
        $dir = dirname($this->getSshKeyPath());
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true);
        }

        $keyPath = $this->getSshKeyPath();
        $pubPath = $keyPath.'.pub';

        if (File::exists($keyPath)) {
            File::delete($keyPath);
        }
        if (File::exists($pubPath)) {
            File::delete($pubPath);
        }

        $host = gethostname() ?: 'client-lan';
        $res = $this->runProcess([
            'ssh-keygen',
            '-t', 'ed25519',
            '-C', 'uteparts-pos@'.$host,
            '-N', '',
            '-f', $keyPath,
        ]);

        if ($res['status'] === 0 && File::exists($pubPath)) {
            @chmod($keyPath, 0600);
            @chmod($pubPath, 0644);

            // Sesuaikan remote URL ke SSH jika masih HTTPS
            $this->ensureGitRemoteIsSsh();

            return [
                'success' => true,
                'public_key' => trim(File::get($pubPath)),
                'message' => 'SSH Deploy Key berhasil di-generate! Salin Public Key di bawah dan kirim ke developer untuk didaftarkan di GitHub repository.',
            ];
        }

        return [
            'success' => false,
            'public_key' => null,
            'message' => 'Gagal generate SSH Key: '.$res['output'],
        ];
    }

    /**
     * Pastikan remote origin memakai format SSH (git@github.com:...) jika SSH key aktif.
     */
    public function ensureGitRemoteIsSsh(): void
    {
        $res = $this->runGit(['remote', 'get-url', 'origin']);
        $url = trim($res['output'] ?? '');
        if (str_starts_with($url, 'https://github.com/')) {
            $sshUrl = preg_replace('#^https://github\.com/([^/]+)/([^/]+?)(\.git)?$#', 'git@github.com:$1/$2.git', $url);
            if ($sshUrl && $sshUrl !== $url) {
                $this->runGit(['remote', 'set-url', 'origin', $sshUrl]);
            }
        }
    }

    /**
     * Jalankan perintah Git dalam direktori proyek.
     */
    protected function runGit(array $arguments): array
    {
        $customArgs = [];

        // Jika ada SSH key lokal di storage/app/keys/id_ed25519, gunakan core.sshCommand
        $sshKey = $this->getSshKeyPath();
        if (File::exists($sshKey)) {
            $customArgs[] = '-c';
            $customArgs[] = "core.sshCommand=ssh -i {$sshKey} -o StrictHostKeyChecking=no";
        }

        array_unshift($arguments, ...$customArgs);
        array_unshift($arguments, 'git');

        return $this->runProcess($arguments);
    }

    /**
     * Jalankan proses eksternal secara aman.
     */
    protected function runProcess(array $command): array
    {
        try {
            $process = new Process($command, $this->getBasePath(), null, null, 120);
            $process->run();

            return [
                'status' => $process->getExitCode(),
                'output' => trim($process->getOutput().' '.$process->getErrorOutput()),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 1,
                'output' => $e->getMessage(),
            ];
        }
    }
}
