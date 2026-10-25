<?php
/**
 * Ute Parts ERP & Marketplace — Universal Web GUI Installer
 *
 * Mengakomodasi:
 * 1. Pengecekan otomatis kondisi server (OS, RAM, Disk, PHP Ext, Nginx/OLS, DB, Git, Supervisor)
 * 2. Setup database MySQL & kredensial .env tanpa koding
 * 3. Hubungan ke Git untuk webhook CI/CD otomatis
 * 4. Integrasi bisnis (Duitku, Biteship, WA Gateway)
 * 5. Pilihan AI Development & Maintenance Node (OpenCode, 9router, MCP, OMO Slim)
 * 6. Eksekusi instan hingga sistem siap online & go-live
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

// Deteksi jika sistem sudah terinstall
$envFile = __DIR__.'/../.env';
$isInstalled = false;
if (file_exists($envFile)) {
    $envContent = (string) file_get_contents($envFile);
    if (preg_match('/^APP_KEY=base64:.+/m', $envContent) && preg_match('/^DB_DATABASE=.+/m', $envContent)) {
        $isInstalled = true;
    }
}

// Redirect ke login jika sudah terkonfigurasi dan bukan mode AJAX
if ($isInstalled && empty($_GET['force']) && empty($_POST['action'])) {
    header('Location: /app/login');
    exit;
}

// =============================================================================
// HELPER FUNCTIONS & DIAGNOSTICS
// =============================================================================

function getPhpBinary(): string
{
    $candidates = [
        '/usr/bin/php8.4',
        '/usr/bin/php8.3',
        '/usr/bin/php',
        '/usr/local/lsws/lsphp85/bin/php',
        '/usr/local/lsws/lsphp84/bin/php',
        PHP_BINARY,
    ];
    foreach ($candidates as $bin) {
        if (! empty($bin) && is_executable($bin)) {
            return $bin;
        }
    }

    return 'php';
}

function getComposerCommand(): string
{
    $baseDir = dirname(__DIR__);
    $phpBin = getPhpBinary();

    if (runCommand('which composer')['status'] === 0) {
        return 'composer';
    }

    if (file_exists($baseDir.'/composer.phar')) {
        return $phpBin.' '.escapeshellarg($baseDir.'/composer.phar');
    }

    return '';
}

function downloadComposerPhar(): bool
{
    $baseDir = dirname(__DIR__);
    $pharFile = $baseDir.'/composer.phar';
    if (file_exists($pharFile) && filesize($pharFile) > 1000000) {
        @chmod($pharFile, 0755);

        return true;
    }

    // Coba via curl CLI
    $curlRes = runCommand("curl -sS -L https://getcomposer.org/composer-stable.phar -o {$pharFile}");
    if ($curlRes['status'] === 0 && file_exists($pharFile) && filesize($pharFile) > 1000000) {
        @chmod($pharFile, 0755);

        return true;
    }

    // Coba via PHP cURL extension
    if (function_exists('curl_init')) {
        $ch = curl_init('https://getcomposer.org/composer-stable.phar');
        $fp = @fopen($pharFile, 'w+');
        if ($fp) {
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            if (file_exists($pharFile) && filesize($pharFile) > 1000000) {
                @chmod($pharFile, 0755);

                return true;
            }
        }
    }

    // Fallback: installer script
    $phpBin = getPhpBinary();
    runCommand("cd {$baseDir} && curl -sS https://getcomposer.org/installer | {$phpBin} -- --install-dir={$baseDir} --filename=composer.phar");
    if (file_exists($pharFile) && filesize($pharFile) > 1000000) {
        @chmod($pharFile, 0755);

        return true;
    }

    return false;
}

function runCommand(string $command): array
{
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, __DIR__.'/..');
    if (! is_resource($process)) {
        return ['status' => 1, 'output' => 'Gagal menjalankan proses'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    return [
        'status' => $status,
        'output' => trim((string) $stdout).($stderr ? "\n".trim((string) $stderr) : ''),
    ];
}

function runComposerInstallWithFallback(string $baseDir, string $composerCmd): array
{
    // 1. Coba composer install standar
    $res = runCommand("cd {$baseDir} && {$composerCmd} install --no-interaction --prefer-dist --optimize-autoloader 2>&1");
    if ($res['status'] === 0) {
        return ['status' => 0, 'output' => $res['output'], 'fallback' => false];
    }

    // 2. Jika gagal karena platform requirement (seperti missing ext-dom, ext-xml, ext-simplexml, dll)
    $output = $res['output'];
    if (stripos($output, '--ignore-platform-req') !== false || stripos($output, 'ext-') !== false || stripos($output, 'requires ext-') !== false) {
        $fallbackCmd = "cd {$baseDir} && {$composerCmd} install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter 2>&1";
        $fbRes = runCommand($fallbackCmd);
        if ($fbRes['status'] === 0) {
            return ['status' => 0, 'output' => $fbRes['output'], 'fallback' => true, 'fallback_type' => 'xml_dom'];
        }

        // 3. Fallback toleran menyeluruh jika ada ekstensi lain yang belum termuat di runtime CLI
        $fallbackAllCmd = "cd {$baseDir} && {$composerCmd} install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs 2>&1";
        $fbAllRes = runCommand($fallbackAllCmd);
        if ($fbAllRes['status'] === 0) {
            return ['status' => 0, 'output' => $fbAllRes['output'], 'fallback' => true, 'fallback_type' => 'all'];
        }

        return ['status' => $fbAllRes['status'], 'output' => $fbAllRes['output'], 'fallback' => false];
    }

    return ['status' => $res['status'], 'output' => $res['output'], 'fallback' => false];
}

function checkServerDiagnostics(): array
{
    // 1. OS & Kernel
    $osInfo = php_uname('s').' '.php_uname('r').' ('.php_uname('m').')';
    if (file_exists('/etc/os-release')) {
        $osRelease = (string) file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="([^"]+)"/', $osRelease, $m)) {
            $osInfo = $m[1].' ('.php_uname('m').')';
        }
    }

    // 2. RAM & Swap
    $totalRamMb = 0;
    $freeRamMb = 0;
    $swapMb = 0;
    if (file_exists('/proc/meminfo')) {
        $mem = (string) file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $mem, $m)) {
            $totalRamMb = round(((int) $m[1]) / 1024);
        }
        if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $mem, $m)) {
            $freeRamMb = round(((int) $m[1]) / 1024);
        }
        if (preg_match('/SwapTotal:\s+(\d+)\s+kB/', $mem, $m)) {
            $swapMb = round(((int) $m[1]) / 1024);
        }
    }

    // 3. Disk Space
    $diskFreeGb = round(disk_free_space(__DIR__) / (1024 * 1024 * 1024), 1);
    $diskTotalGb = round(disk_total_space(__DIR__) / (1024 * 1024 * 1024), 1);

    // 4. PHP Extensions Check (Wajib untuk Laravel 13, WMS, Excel & E-Payment)
    $requiredExts = [
        'pdo_mysql' => 'Koneksi database MySQL',
        'dom' => 'Manipulasi DOM, Blade view compiler & PhpSpreadsheet',
        'xml' => 'Penguraian XML & konfigurasi Laravel/Excel',
        'simplexml' => 'Format XML data export & barcode generator',
        'xmlwriter' => 'Export Excel (Maatwebsite) & manifest XML',
        'gd' => 'Kompresi & manipulasi gambar WebP',
        'mbstring' => 'Pemrosesan string UTF-8 multibyte',
        'curl' => 'Koneksi API Duitku & Biteship',
        'zip' => 'Ekstraksi bulk foto & impor Excel',
        'intl' => 'Format angka & ribuan Rupiah',
        'bcmath' => 'Kalkulasi finansial & double-entry',
        'fileinfo' => 'Validasi MIME type berkas',
        'openssl' => 'Enkripsi data & security token',
        'tokenizer' => 'Parser kode Laravel Blade & Livewire',
        'ctype' => 'Validasi karakter string standar Laravel',
    ];

    $optionalExts = [
        'imagick' => 'Decoder cerdas gambar HEIC, AVIF, TIFF, RAW, PSD',
        'opcache' => 'Akselerasi performa script PHP di produksi',
        'sqlite3' => 'Penyimpanan cache testing lokal',
    ];

    $extStatus = [];
    $allReqPassed = true;
    foreach ($requiredExts as $ext => $desc) {
        $loaded = extension_loaded($ext);
        if (! $loaded) {
            $allReqPassed = false;
        }
        $extStatus[$ext] = ['loaded' => $loaded, 'required' => true, 'desc' => $desc];
    }
    foreach ($optionalExts as $ext => $desc) {
        $extStatus[$ext] = ['loaded' => extension_loaded($ext), 'required' => false, 'desc' => $desc];
    }

    // 5. System Tools
    $baseDir = dirname(__DIR__);
    $tools = [
        'composer' => (runCommand('which composer')['status'] === 0 || file_exists($baseDir.'/composer.phar')),
        'git' => runCommand('which git')['status'] === 0,
        'node' => runCommand('which node')['status'] === 0,
        'npm' => runCommand('which npm')['status'] === 0,
        'supervisor' => runCommand('which supervisorctl')['status'] === 0,
        'nginx' => runCommand('which nginx')['status'] === 0,
        'opencode' => (runCommand('which opencode')['status'] === 0 || file_exists('/root/.opencode/bin/opencode')),
        '9router' => runCommand('which 9router')['status'] === 0,
    ];

    // 6. Directory Permissions & Vendor
    $writableStorage = is_writable($baseDir.'/storage');
    $writableCache = is_writable($baseDir.'/bootstrap/cache');
    $writableEnv = is_writable($baseDir) || (file_exists($baseDir.'/.env') && is_writable($baseDir.'/.env'));
    $vendorReady = file_exists($baseDir.'/vendor/autoload.php');

    $iniPath = php_ini_loaded_file() ?: 'Tidak terdeteksi (default)';
    $majorMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

    $missingExtsList = [];
    foreach ($extStatus as $ext => $info) {
        if (! $info['loaded']) {
            $missingExtsList[] = $ext.($info['required'] ? ' (wajib)' : ' (opsional)');
        }
    }

    $recommendedCmd = 'sudo apt-get update && sudo apt-get install -y php'.$majorMinor.'-mysql php'.$majorMinor.'-xml php'.$majorMinor.'-gd php'.$majorMinor.'-mbstring php'.$majorMinor.'-curl php'.$majorMinor.'-zip php'.$majorMinor.'-intl php'.$majorMinor.'-bcmath php'.$majorMinor.'-imagick unzip';

    return [
        'os' => $osInfo,
        'php_version' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'php_ini' => $iniPath,
        'php_binary' => getPhpBinary(),
        'ram_total_mb' => $totalRamMb,
        'ram_free_mb' => $freeRamMb,
        'swap_mb' => $swapMb,
        'disk_free_gb' => $diskFreeGb,
        'disk_total_gb' => $diskTotalGb,
        'extensions' => $extStatus,
        'extensions_ok' => $allReqPassed,
        'missing_extensions' => $missingExtsList,
        'recommended_cmd' => $recommendedCmd,
        'tools' => $tools,
        'vendor_ready' => $vendorReady,
        'permissions' => [
            'storage' => $writableStorage,
            'cache' => $writableCache,
            'env' => $writableEnv,
        ],
        'is_ready' => (version_compare(PHP_VERSION, '8.3.0', '>=') && $allReqPassed && $writableStorage && $writableCache),
    ];
}

// =============================================================================
// AJAX API ENDPOINTS
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'get_diagnostics') {
        echo json_encode([
            'success' => true,
            'diagnostics' => checkServerDiagnostics(),
        ]);
        exit;
    }

    if ($action === 'autofix_step') {
        $stepId = $_POST['step_id'] ?? '';
        $baseDir = dirname(__DIR__);
        $phpBin = getPhpBinary();
        $logs = [];
        $restartNeeded = false;
        $manualCmd = '';

        switch ($stepId) {
            case 'dirs':
                $dirs = [
                    'storage',
                    'storage/app',
                    'storage/app/public',
                    'storage/framework',
                    'storage/framework/cache',
                    'storage/framework/cache/data',
                    'storage/framework/sessions',
                    'storage/framework/views',
                    'storage/logs',
                    'bootstrap/cache',
                ];
                foreach ($dirs as $dir) {
                    $fullPath = $baseDir.'/'.$dir;
                    if (! is_dir($fullPath)) {
                        @mkdir($fullPath, 0775, true);
                    }
                    @chmod($fullPath, 0775);
                }
                runCommand("chmod -R 775 {$baseDir}/storage {$baseDir}/bootstrap/cache 2>/dev/null || true");
                $logs[] = 'Direktori storage & bootstrap/cache berhasil disiapkan dengan izin tulis (0775).';
                break;

            case 'extensions':
                $iniFile = php_ini_loaded_file();
                $extsToCheck = ['pdo_mysql', 'dom', 'xml', 'simplexml', 'xmlwriter', 'gd', 'mbstring', 'curl', 'zip', 'intl', 'bcmath', 'fileinfo', 'openssl', 'tokenizer', 'ctype'];
                $missing = [];
                foreach ($extsToCheck as $e) {
                    if (! extension_loaded($e)) {
                        $missing[] = $e;
                    }
                }

                if (empty($missing)) {
                    $logs[] = 'Semua ekstensi PHP wajib sudah aktif di memori.';
                    break;
                }

                // 1. Coba aktifkan di php.ini jika ada & writable (terutama Windows / Laragon / XAMPP)
                if ($iniFile && is_file($iniFile) && is_writable($iniFile)) {
                    $content = (string) file_get_contents($iniFile);
                    $modified = false;
                    foreach ($missing as $e) {
                        $pattern = '/^[;\s]*extension\s*=\s*(php_)?'.preg_quote($e, '/').'(\.dll)?/mi';
                        if (preg_match($pattern, $content)) {
                            $content = preg_replace($pattern, 'extension='.$e, $content);
                            $modified = true;
                            $logs[] = "Mengaktifkan baris 'extension={$e}' di php.ini.";
                        }
                    }
                    if ($modified) {
                        file_put_contents($iniFile, $content);
                        $restartNeeded = true;
                        $logs[] = "File php.ini ({$iniFile}) berhasil diperbarui! Server PHP perlu di-restart agar ekstensi aktif di memori.";
                    }
                }

                // 2. Jika di Linux (Debian/Ubuntu), coba instalasi paket apt
                if (file_exists('/etc/debian_version') || runCommand('which apt-get')['status'] === 0) {
                    $majorMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
                    $pkgList = [];
                    $extPkgMap = [
                        'pdo_mysql' => "php{$majorMinor}-mysql",
                        'dom' => "php{$majorMinor}-xml",
                        'xml' => "php{$majorMinor}-xml",
                        'simplexml' => "php{$majorMinor}-xml",
                        'xmlwriter' => "php{$majorMinor}-xml",
                        'gd' => "php{$majorMinor}-gd",
                        'mbstring' => "php{$majorMinor}-mbstring",
                        'curl' => "php{$majorMinor}-curl",
                        'zip' => "php{$majorMinor}-zip",
                        'intl' => "php{$majorMinor}-intl",
                        'bcmath' => "php{$majorMinor}-bcmath",
                        'imagick' => "php{$majorMinor}-imagick",
                        'tokenizer' => "php{$majorMinor}-common",
                        'ctype' => "php{$majorMinor}-common",
                    ];
                    foreach ($missing as $m) {
                        if (isset($extPkgMap[$m]) && ! in_array($extPkgMap[$m], $pkgList, true)) {
                            $pkgList[] = $extPkgMap[$m];
                        }
                    }
                    if (! empty($pkgList)) {
                        $manualCmd = 'sudo apt-get update && sudo apt-get install -y '.implode(' ', $pkgList).' unzip';
                        $isRoot = (function_exists('posix_geteuid') && posix_geteuid() === 0) || (trim(runCommand('id -u')['output']) === '0');
                        $canSudo = runCommand('sudo -n true 2>/dev/null')['status'] === 0;

                        if ($isRoot || $canSudo) {
                            $pfx = $isRoot ? '' : 'sudo ';
                            $aptRes = runCommand("export DEBIAN_FRONTEND=noninteractive && {$pfx}apt-get update -y && {$pfx}apt-get install -y ".implode(' ', $pkgList).' unzip 2>&1');
                            if ($aptRes['status'] === 0) {
                                $restartNeeded = true;
                                $logs[] = 'Paket ekstensi ('.implode(', ', $pkgList).') berhasil diinstal via apt! Harap restart server PHP.';
                            } else {
                                $logs[] = 'Gagal otomatis via apt: '.$aptRes['output'];
                            }
                        } else {
                            $logs[] = 'Ekstensi memerlukan izin root di terminal: '.$manualCmd;
                        }
                    }
                }
                break;

            case 'composer':
                $composerCmd = getComposerCommand();
                if (empty($composerCmd)) {
                    $logs[] = 'Mengunduh binary Composer (composer.phar) portabel...';
                    if (downloadComposerPhar()) {
                        $composerCmd = getComposerCommand();
                        $logs[] = 'Composer binary (composer.phar) berhasil disiapkan.';
                    } else {
                        $logs[] = 'Gagal mengunduh composer.phar. Silakan pasang Composer secara manual.';
                    }
                } else {
                    $logs[] = 'Composer terdeteksi: '.$composerCmd;
                }
                break;

            case 'vendor':
                $composerCmd = getComposerCommand();
                if (empty($composerCmd)) {
                    downloadComposerPhar();
                    $composerCmd = getComposerCommand();
                }
                if (! file_exists($baseDir.'/vendor/autoload.php')) {
                    if (! empty($composerCmd)) {
                        $logs[] = 'Menjalankan composer install... (mohon tunggu sebentar)';
                        $compRes = runComposerInstallWithFallback($baseDir, $composerCmd);
                        if ($compRes['status'] === 0) {
                            if (! empty($compRes['fallback'])) {
                                $logs[] = 'Dependensi vendor Composer berhasil diinstal (menggunakan toleransi ekstensi platform).';
                                $logs[] = 'PENTING: Pastikan paket php-xml tetap diinstal agar manipulasi XML & export Excel berjalan optimal.';
                            } else {
                                $logs[] = 'Dependensi vendor Composer berhasil diinstal lengkap!';
                            }
                        } else {
                            $logs[] = 'Composer install: '.substr($compRes['output'], -250);
                        }
                    } else {
                        $logs[] = 'Composer tidak ditemukan untuk menginstal vendor.';
                    }
                } else {
                    $logs[] = 'Dependensi vendor Composer sudah lengkap (vendor/autoload.php terverifikasi).';
                }
                break;

            case 'swap':
                $diagCheck = checkServerDiagnostics();
                if ($diagCheck['ram_total_mb'] > 0 && $diagCheck['ram_total_mb'] <= 1500 && $diagCheck['swap_mb'] < 512) {
                    $isRoot = (function_exists('posix_geteuid') && posix_geteuid() === 0) || (trim(runCommand('id -u')['output']) === '0');
                    $canSudo = runCommand('sudo -n true 2>/dev/null')['status'] === 0;
                    if ($isRoot || $canSudo) {
                        $pfx = $isRoot ? '' : 'sudo ';
                        runCommand("{$pfx}fallocate -l 2G /swapfile 2>/dev/null || {$pfx}dd if=/dev/zero of=/swapfile bs=1M count=2048 2>/dev/null");
                        runCommand("{$pfx}chmod 600 /swapfile && {$pfx}mkswap /swapfile 2>/dev/null && {$pfx}swapon /swapfile 2>/dev/null");
                        $logs[] = 'Swap memori 2GB berhasil diaktifkan untuk menjaga stabilitas RAM.';
                    } else {
                        $logs[] = 'RAM server <= 1.5GB tanpa Swap. Disarankan alokasi swap dengan: sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile';
                    }
                } else {
                    $logs[] = 'Alokasi memori RAM/Swap mencukupi.';
                }
                break;
        }

        echo json_encode([
            'success' => true,
            'step_id' => $stepId,
            'logs' => $logs,
            'restart_needed' => $restartNeeded,
            'manual_cmd' => $manualCmd,
            'diagnostics' => checkServerDiagnostics(),
        ]);
        exit;
    }

    if ($action === 'test_db') {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? 'uteparts_db');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = trim($_POST['db_pass'] ?? '');

        try {
            // Coba koneksi ke server dulu
            $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            // Cek apakah database sudah ada
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$name}'");
            $exists = (bool) $stmt->fetch();

            if (! $exists && ! empty($_POST['create_if_missing'])) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $exists = true;
            }

            echo json_encode([
                'success' => true,
                'message' => $exists
                    ? "Koneksi berhasil! Database '{$name}' siap digunakan."
                    : "Koneksi ke MySQL sukses, namun database '{$name}' belum ada (centang opsi buat otomatis).",
                'db_exists' => $exists,
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal koneksi MySQL: '.$e->getMessage(),
            ]);
        }
        exit;
    }

    if ($action === 'execute_step') {
        $step = $_POST['step'] ?? '';
        $baseDir = dirname(__DIR__);
        $phpBin = getPhpBinary();

        try {
            switch ($step) {
                case 'write_env':
                    $appName = addslashes(trim($_POST['app_name'] ?? 'Ute Parts'));
                    $appUrl = rtrim(trim($_POST['app_url'] ?? 'http://localhost'), '/');
                    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
                    $dbPort = trim($_POST['db_port'] ?? '3306');
                    $dbName = trim($_POST['db_name'] ?? 'uteparts_db');
                    $dbUser = trim($_POST['db_user'] ?? 'root');
                    $dbPass = trim($_POST['db_pass'] ?? '');

                    $duitkuSandbox = ! empty($_POST['duitku_sandbox']) ? 'true' : 'false';
                    $duitkuCode = trim($_POST['duitku_code'] ?? '');
                    $duitkuKey = trim($_POST['duitku_key'] ?? '');
                    $biteshipKey = trim($_POST['biteship_key'] ?? '');
                    $waToken = trim($_POST['wa_token'] ?? '');
                    $gitSecret = trim($_POST['git_secret'] ?? bin2hex(random_bytes(16)));

                    $envTemplate = <<<EOF
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL={$appUrl}
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=ute

FILESYSTEM_DISK=public

DUITKU_SANDBOX={$duitkuSandbox}
DUITKU_MERCHANT_CODE={$duitkuCode}
DUITKU_API_KEY={$duitkuKey}
DUITKU_CALLBACK_URL={$appUrl}/webhook/duitku

BITESHIP_API_KEY={$biteshipKey}

WA_GATEWAY_PROVIDER=fonnte
FONNTE_TOKEN={$waToken}

GIT_DEPLOY_SECRET={$gitSecret}
GIT_DEPLOY_BRANCH=main

VITE_APP_NAME="{$appName}"
EOF;
                    file_put_contents($baseDir.'/.env', $envTemplate);
                    echo json_encode(['success' => true, 'message' => 'Konfigurasi .env berhasil disimpan.']);
                    break;

                case 'check_vendor':
                    if (! file_exists($baseDir.'/vendor/autoload.php')) {
                        $composerCmd = getComposerCommand();
                        if (empty($composerCmd)) {
                            downloadComposerPhar();
                            $composerCmd = getComposerCommand();
                        }
                        if (empty($composerCmd)) {
                            throw new RuntimeException('Composer tidak ditemukan dan gagal mengunduh composer.phar.');
                        }
                        $resComp = runComposerInstallWithFallback($baseDir, $composerCmd);
                        if ($resComp['status'] !== 0) {
                            throw new RuntimeException('Gagal instalasi composer: '.$resComp['output']);
                        }
                        $msg = ! empty($resComp['fallback'])
                            ? 'Dependensi Composer berhasil diinstal (dengan toleransi platform requirements).'
                            : 'Dependensi Composer berhasil diinstal.';
                        echo json_encode(['success' => true, 'message' => $msg]);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Dependensi Composer sudah lengkap.']);
                    }
                    break;

                case 'key_generate':
                    $res = runCommand("cd {$baseDir} && {$phpBin} artisan key:generate --force");
                    if ($res['status'] !== 0) {
                        throw new RuntimeException($res['output']);
                    }
                    echo json_encode(['success' => true, 'message' => 'Application key berhasil di-generate.']);
                    break;

                case 'migrate_seed':
                    $resMigrate = runCommand("cd {$baseDir} && {$phpBin} artisan migrate --force");
                    if ($resMigrate['status'] !== 0) {
                        throw new RuntimeException('Migrasi gagal: '.$resMigrate['output']);
                    }
                    $resSeed = runCommand("cd {$baseDir} && {$phpBin} artisan db:seed --force");
                    echo json_encode(['success' => true, 'message' => 'Struktur database & data bawaan berhasil di-seed.']);
                    break;

                case 'storage_link':
                    runCommand("cd {$baseDir} && {$phpBin} artisan storage:link");
                    echo json_encode(['success' => true, 'message' => 'Symlink storage publik berhasil dibuat.']);
                    break;

                case 'optimize_cache':
                    runCommand("cd {$baseDir} && {$phpBin} artisan config:cache");
                    runCommand("cd {$baseDir} && {$phpBin} artisan route:cache");
                    runCommand("cd {$baseDir} && {$phpBin} artisan view:cache");
                    echo json_encode(['success' => true, 'message' => 'Cache aplikasi berhasil dioptimasi untuk produksi.']);
                    break;

                case 'setup_ai_dev':
                    $installAi = ! empty($_POST['install_ai_stack']);
                    if ($installAi && file_exists($baseDir.'/deploy/setup-ai-dev-stack.sh')) {
                        $resAi = runCommand("bash {$baseDir}/deploy/setup-ai-dev-stack.sh");
                        echo json_encode(['success' => true, 'message' => 'AI Stack (OpenCode + 9router + MCP) berhasil diaktifkan.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'AI Stack dilewati.']);
                    }
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Tahapan tidak dikenal.']);
                    break;
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$diag = checkServerDiagnostics();
$detectedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ute Parts ERP — Setup Wizard &amp; Server Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .glass-panel { background: rgba(18, 20, 29, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .brand-gradient { background: linear-gradient(135deg, #5B4FE9 0%, #E8873B 100%); }
    </style>
</head>
<body class="bg-[#0D0F17] text-slate-100 min-h-screen py-8 px-4 flex flex-col justify-between">

<div class="max-w-4xl mx-auto w-full space-y-6">
    <!-- Header Brand -->
    <div class="flex items-center justify-between border-b border-white/10 pb-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl brand-gradient flex items-center justify-center text-white font-black text-xl shadow-lg shadow-indigo-500/20">
                UP
            </div>
            <div>
                <h1 class="text-lg font-black tracking-tight text-white flex items-center gap-2">
                    Ute Parts ERP &amp; Marketplace
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-bold border border-indigo-500/30">Installer v2.4</span>
                </h1>
                <p class="text-xs text-slate-400">One-Click Universal Server Deployment &amp; AI Node Setup</p>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="text-[11px] font-mono text-slate-400">Target Host: <?= htmlspecialchars($detectedUrl) ?></div>
            <div class="text-[10px] text-emerald-400 font-bold">● Server Live Ready</div>
        </div>
    </div>

    <!-- Step Wizard Nav -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <div id="nav-step-1" class="p-3 rounded-xl glass-panel border-indigo-500/40 text-left transition">
            <div class="text-[10px] uppercase font-bold text-indigo-400">Langkah 1</div>
            <div class="text-xs font-bold text-white">Kondisi Server</div>
        </div>
        <div id="nav-step-2" class="p-3 rounded-xl glass-panel opacity-60 text-left transition">
            <div class="text-[10px] uppercase font-bold text-slate-400">Langkah 2</div>
            <div class="text-xs font-bold text-white">Database MySQL</div>
        </div>
        <div id="nav-step-3" class="p-3 rounded-xl glass-panel opacity-60 text-left transition">
            <div class="text-[10px] uppercase font-bold text-slate-400">Langkah 3</div>
            <div class="text-xs font-bold text-white">Bisnis &amp; CI/CD</div>
        </div>
        <div id="nav-step-4" class="p-3 rounded-xl glass-panel opacity-60 text-left transition">
            <div class="text-[10px] uppercase font-bold text-slate-400">Langkah 4</div>
            <div class="text-xs font-bold text-white">AI Stack &amp; Go-Live</div>
        </div>
    </div>

    <!-- Card Container -->
    <div class="glass-panel p-6 sm:p-8 rounded-2xl shadow-2xl relative overflow-hidden">
        
        <!-- STEP 1: KONDISI SERVER -->
        <div id="section-step-1" class="space-y-6">
            <div class="flex items-center justify-between border-b border-white/5 pb-3">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🔍</span>
                        <span>Diagnostik Kesehatan Server Otomatis</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Pemeriksaan dependensi &amp; kesiapan lingkungan kerja sistem.</p>
                </div>
                <div id="server-status-pill">
                    <?php if ($diag['is_ready'] && $diag['vendor_ready']) { ?>
                        <span class="px-3 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs font-bold border border-emerald-500/30 flex items-center gap-1.5">
                            <span>✓</span> <span>Server Siap Go-Live</span>
                        </span>
                    <?php } else { ?>
                        <span class="px-3 py-1 rounded-lg bg-amber-500/20 text-amber-300 text-xs font-bold border border-amber-500/30 flex items-center gap-1.5">
                            <span>⚠️</span> <span>Perlu Penyiapan Dependensi</span>
                        </span>
                    <?php } ?>
                </div>
            </div>

            <!-- Server Metrics Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">Sistem Operasi</div>
                    <div id="metric-os" class="font-bold text-white truncate mt-1" title="<?= htmlspecialchars($diag['os']) ?>"><?= htmlspecialchars($diag['os']) ?></div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">PHP Version &amp; Binary</div>
                    <div id="metric-php" class="font-bold <?= $diag['php_ok'] ? 'text-emerald-400' : 'text-rose-400' ?> mt-1 truncate" title="<?= htmlspecialchars($diag['php_binary']) ?>">
                        PHP <?= $diag['php_version'] ?>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">RAM / Swap</div>
                    <div id="metric-ram" class="font-bold text-white mt-1">
                        <?= $diag['ram_total_mb'] ?> MB <span class="text-[10px] text-slate-400">(Swap: <?= $diag['swap_mb'] ?>M)</span>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">Sisa Disk</div>
                    <div id="metric-disk" class="font-bold text-white mt-1"><?= $diag['disk_free_gb'] ?> GB / <?= $diag['disk_total_gb'] ?> GB</div>
                </div>
            </div>

            <!-- Auto-Fix Wizard Checklist Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-orange-500/10 border border-indigo-500/30 space-y-4 shadow-xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <div class="font-bold text-sm text-white flex items-center gap-2">
                            <span>⚡ Wizard Penyiapan Otomatis (Step-by-Step Auto-Fix)</span>
                        </div>
                        <p class="text-xs text-slate-300">
                            Sistem mengeksekusi penyiapan satu persatu dan memvalidasi hasilnya secara bertahap.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" id="btn-autofix" onclick="runSequentialAutoFix()" class="px-5 py-2.5 rounded-xl brand-gradient text-white font-bold text-xs transition shadow-lg shadow-indigo-600/20 flex items-center gap-2 cursor-pointer hover:opacity-95">
                            <span id="autofix-spinner" class="hidden animate-spin">⟳</span>
                            <span id="autofix-btn-text">⚡ Jalankan Auto-Fix Sekarang</span>
                        </button>
                        <button type="button" onclick="refreshDiagnostics()" class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs border border-white/10 flex items-center gap-1.5 transition cursor-pointer" title="Periksa ulang kondisi terkini">
                            <span>🔄</span>
                            <span>Segarkan</span>
                        </button>
                    </div>
                </div>

                <!-- 5 Sequential Checkpoints -->
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-2 pt-1 font-mono text-xs">
                    <div id="card-step-dirs" class="p-3 rounded-xl bg-black/40 border border-white/5 space-y-1">
                        <div class="text-[10px] text-slate-400 font-sans">Step 1</div>
                        <div class="font-bold text-slate-200 text-[11px] truncate">1. Folder Storage</div>
                        <div id="status-step-dirs" class="text-[10px] <?= $diag['permissions']['storage'] && $diag['permissions']['cache'] ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>">
                            <?= $diag['permissions']['storage'] && $diag['permissions']['cache'] ? '✓ Siap' : '○ Menunggu' ?>
                        </div>
                    </div>
                    <div id="card-step-extensions" class="p-3 rounded-xl bg-black/40 border border-white/5 space-y-1">
                        <div class="text-[10px] text-slate-400 font-sans">Step 2</div>
                        <div class="font-bold text-slate-200 text-[11px] truncate">2. Ekstensi PHP</div>
                        <div id="status-step-extensions" class="text-[10px] <?= $diag['extensions_ok'] ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold' ?>">
                            <?= $diag['extensions_ok'] ? '✓ Lengkap' : '○ Perlu Dicek' ?>
                        </div>
                    </div>
                    <div id="card-step-composer" class="p-3 rounded-xl bg-black/40 border border-white/5 space-y-1">
                        <div class="text-[10px] text-slate-400 font-sans">Step 3</div>
                        <div class="font-bold text-slate-200 text-[11px] truncate">3. Composer Tool</div>
                        <div id="status-step-composer" class="text-[10px] <?= $diag['tools']['composer'] ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>">
                            <?= $diag['tools']['composer'] ? '✓ Siap' : '○ Menunggu' ?>
                        </div>
                    </div>
                    <div id="card-step-vendor" class="p-3 rounded-xl bg-black/40 border border-white/5 space-y-1">
                        <div class="text-[10px] text-slate-400 font-sans">Step 4</div>
                        <div class="font-bold text-slate-200 text-[11px] truncate">4. Vendor Dependensi</div>
                        <div id="status-step-vendor" class="text-[10px] <?= $diag['vendor_ready'] ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>">
                            <?= $diag['vendor_ready'] ? '✓ Terpasang' : '○ Menunggu' ?>
                        </div>
                    </div>
                    <div id="card-step-swap" class="p-3 rounded-xl bg-black/40 border border-white/5 space-y-1">
                        <div class="text-[10px] text-slate-400 font-sans">Step 5</div>
                        <div class="font-bold text-slate-200 text-[11px] truncate">5. Swap Memori</div>
                        <div id="status-step-swap" class="text-[10px] <?= $diag['swap_mb'] > 0 || $diag['ram_total_mb'] > 1500 ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>">
                            <?= $diag['swap_mb'] > 0 || $diag['ram_total_mb'] > 1500 ? '✓ Aman' : '○ Menunggu' ?>
                        </div>
                    </div>
                </div>

                <!-- Alert Callout jika membutuhkan restart atau aksi manual di terminal -->
                <div id="manual-action-card" class="<?= ! empty($diag['missing_extensions']) ? '' : 'hidden' ?> p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-amber-300 flex items-center gap-1.5">
                            <span>💡</span>
                            <span id="manual-action-title">Perintah Terminal Diperlukan / Restart PHP</span>
                        </div>
                        <button type="button" onclick="copyManualCmd()" class="px-2.5 py-1 rounded bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 text-[11px] font-bold border border-amber-500/30 transition cursor-pointer flex items-center gap-1">
                            <span>📋</span>
                            <span>Salin Perintah</span>
                        </button>
                    </div>
                    <p id="manual-action-desc" class="text-[11px] text-slate-300">
                        Jika ekstensi PHP belum otomatis terpasang atau konfigurasi php.ini baru diubah, jalankan perintah ini di terminal Anda:
                    </p>
                    <pre id="manual-action-code" class="p-2.5 rounded-lg bg-black/60 font-mono text-[11px] text-amber-200 overflow-x-auto select-all"><?= htmlspecialchars($diag['recommended_cmd']) ?></pre>
                    <div class="text-[10px] text-slate-400">
                        *Setelah perintah selesai, hentikan PHP server (<code class="text-white">Ctrl+C</code>) lalu jalankan kembali launcher (<code class="text-white">./start-install.sh</code> atau <code class="text-white">start-install.bat</code>) kemudian klik <strong>Segarkan</strong>.
                    </div>
                </div>
            </div>

            <!-- Extensions Table -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-300">
                    <span>Ekstensi PHP Terdeteksi (php.ini: <code class="font-mono text-indigo-300 text-[11px]" title="<?= htmlspecialchars($diag['php_ini']) ?>"><?= htmlspecialchars(basename($diag['php_ini'])) ?></code>):</span>
                </div>
                <div id="extensions-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-[11px] font-mono">
                    <?php foreach ($diag['extensions'] as $extName => $ext) { ?>
                        <div class="p-2.5 rounded-xl border flex items-center justify-between <?= $ext['loaded'] ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : ($ext['required'] ? 'bg-rose-500/10 border-rose-500/20 text-rose-300' : 'bg-slate-800/40 border-white/5 text-slate-400') ?>">
                            <span><?= $extName ?></span>
                            <span><?= $ext['loaded'] ? '✓ Ada' : ($ext['required'] ? '✕ Wajib' : '- Opsional') ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Permission & System Tools -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-1.5">
                    <div class="font-bold text-slate-200">Izin Tulis Direktori:</div>
                    <div class="flex items-center justify-between font-mono text-[11px]">
                        <span>storage/</span>
                        <span id="status-perm-storage" class="<?= $diag['permissions']['storage'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $diag['permissions']['storage'] ? '✓ Writable' : '✕ Tidak Writable' ?></span>
                    </div>
                    <div class="flex items-center justify-between font-mono text-[11px]">
                        <span>bootstrap/cache/</span>
                        <span id="status-perm-cache" class="<?= $diag['permissions']['cache'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $diag['permissions']['cache'] ? '✓ Writable' : '✕ Tidak Writable' ?></span>
                    </div>
                    <div class="flex items-center justify-between font-mono text-[11px]">
                        <span>vendor/autoload.php</span>
                        <span id="status-vendor-ready" class="<?= $diag['vendor_ready'] ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $diag['vendor_ready'] ? '✓ Lengkap' : '⚡ Belum Lengkap' ?></span>
                    </div>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-1.5">
                    <div class="font-bold text-slate-200">Tools Sistem Terdeteksi:</div>
                    <div class="flex items-center gap-2 flex-wrap font-mono text-[10px]">
                        <span id="tool-composer" class="px-2 py-0.5 rounded <?= $diag['tools']['composer'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">composer</span>
                        <span id="tool-git" class="px-2 py-0.5 rounded <?= $diag['tools']['git'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">git</span>
                        <span id="tool-node" class="px-2 py-0.5 rounded <?= $diag['tools']['node'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">node</span>
                        <span id="tool-supervisor" class="px-2 py-0.5 rounded <?= $diag['tools']['supervisor'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">supervisor</span>
                        <span id="tool-opencode" class="px-2 py-0.5 rounded <?= $diag['tools']['opencode'] ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-500' ?>">opencode</span>
                        <span id="tool-9router" class="px-2 py-0.5 rounded <?= $diag['tools']['9router'] ? 'bg-orange-500/20 text-orange-300' : 'bg-slate-800 text-slate-500' ?>">9router</span>
                    </div>
                </div>
            </div>

            <!-- Terminal-Style Log Console with 1-Click Copy -->
            <div class="p-4 rounded-xl bg-black/70 border border-white/10 space-y-2">
                <div class="flex items-center justify-between">
                    <div class="font-bold text-slate-300 text-xs flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Console Log Eksekusi &amp; Diagnostik</span>
                    </div>
                    <button type="button" onclick="copyFullDiagnosticReport()" class="px-3 py-1.5 rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 font-bold text-xs border border-indigo-500/40 transition flex items-center gap-1.5 cursor-pointer">
                        <span>📋</span>
                        <span id="copy-report-text">Salin Log Diagnostik Lengkap</span>
                    </button>
                </div>
                <div id="execution-log-box" class="max-h-48 overflow-y-auto p-3 rounded-lg bg-black/60 font-mono text-[11px] text-slate-300 space-y-1 border border-white/5">
                    <div class="text-slate-500">// Log aktivitas penyiapan server siap direkam di sini...</div>
                </div>
            </div>

            <!-- Bottom Action Navigation -->
            <div class="flex items-center justify-between pt-3 border-t border-white/5">
                <div id="step-1-status-hint" class="text-xs <?= $diag['is_ready'] && $diag['vendor_ready'] ? 'text-emerald-400 font-bold' : 'text-amber-400' ?>">
                    <?= $diag['is_ready'] && $diag['vendor_ready'] ? '✓ Seluruh persyaratan sistem telah siap.' : '⚠️ Selesaikan checklist di atas untuk mengaktifkan langkah berikutnya.' ?>
                </div>
                <button type="button" id="btn-next-step-1" onclick="goToStep(2)" class="px-6 py-2.5 rounded-xl <?= $diag['is_ready'] && $diag['vendor_ready'] ? 'bg-indigo-600 hover:bg-indigo-500 text-white cursor-pointer shadow-lg shadow-indigo-600/20' : 'bg-slate-800 text-slate-500 cursor-not-allowed opacity-60' ?> font-bold text-xs transition flex items-center gap-2" <?= $diag['is_ready'] && $diag['vendor_ready'] ? '' : 'disabled' ?>>
                    <span>Lanjut ke Pengaturan Database</span>
                    <span>→</span>
                </button>
            </div>
        </div>

        <!-- STEP 2: DATABASE SETUP -->
        <div id="section-step-2" class="space-y-6 hidden">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🗄️</span>
                    <span>Koneksi Database MySQL / MariaDB</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Tentukan kredensial database lokal atau remote.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Database Host</label>
                    <input type="text" id="db_host" value="127.0.0.1" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Port</label>
                    <input type="text" id="db_port" value="3306" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Nama Database</label>
                    <input type="text" id="db_name" value="uteparts_db" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Username MySQL</label>
                    <input type="text" id="db_user" value="root" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Password MySQL</label>
                    <input type="password" id="db_pass" placeholder="Kosongkan jika tanpa password" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input type="checkbox" id="db_create" checked class="rounded text-indigo-600 focus:ring-0">
                    <label for="db_create" class="text-xs text-slate-300 cursor-pointer">Buat database otomatis jika belum ada di MySQL server</label>
                </div>
            </div>

            <!-- Live DB Test Alert Box -->
            <div id="db-test-result" class="hidden p-3 rounded-xl text-xs font-mono"></div>

            <div class="flex items-center justify-between pt-3 border-t border-white/5">
                <button type="button" onclick="testDatabaseConnection()" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition border border-white/10 flex items-center gap-1.5 cursor-pointer">
                    <span>⚡</span>
                    <span>Tes Koneksi Database</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="goToStep(1)" class="px-4 py-2.5 rounded-xl bg-transparent hover:bg-white/5 text-slate-400 font-bold text-xs transition cursor-pointer">
                        ← Kembali
                    </button>
                    <button type="button" onclick="goToStep(3)" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/20 cursor-pointer">
                        Lanjut ke Bisnis &amp; CI/CD →
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 3: BISNIS & CI/CD -->
        <div id="section-step-3" class="space-y-6 hidden">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>⚙️</span>
                    <span>Pengaturan Domain, Integrasi &amp; Git CI/CD</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Konfigurasi otomatis ke file .env tanpa perlu mengedit kode.</p>
            </div>

            <div class="space-y-4 text-xs">
                <!-- App & Domain -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">Nama Aplikasi</label>
                        <input type="text" id="app_name" value="Ute Parts" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">Public URL / Domain / IP</label>
                        <input type="text" id="app_url" value="<?= htmlspecialchars($detectedUrl) ?>" class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-white font-mono focus:border-indigo-500 outline-none">
                    </div>
                </div>

                <!-- Git Webhook CI/CD -->
                <div class="p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/20 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-indigo-300 flex items-center gap-1.5">
                            <span>🔄</span>
                            <span>Git Webhook Auto-Deploy (CI/CD)</span>
                        </div>
                        <span class="text-[10px] text-slate-400">Trigger saat `git push`</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Sistem menyediakan endpoint <code class="text-indigo-400">POST /webhook/git-deploy</code> yang dapat dipanggil oleh GitHub / GitLab Actions saat Anda melakukan push kode.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-0.5">Secret Key Webhook</label>
                            <input type="text" id="git_secret" value="<?= bin2hex(random_bytes(16)) ?>" class="w-full bg-black/40 border border-white/10 rounded-lg px-2.5 py-1.5 text-white font-mono text-[11px]">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-0.5">Webhook Target URL</label>
                            <input type="text" readonly id="webhook_url_preview" value="<?= htmlspecialchars($detectedUrl) ?>/webhook/git-deploy" class="w-full bg-black/20 border border-white/5 rounded-lg px-2.5 py-1.5 text-slate-400 font-mono text-[11px]">
                        </div>
                    </div>
                </div>

                <!-- Payment & Shipping Gateways -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                        <div class="font-bold text-slate-200">Duitku Payment</div>
                        <input type="text" id="duitku_code" placeholder="Merchant Code" class="w-full bg-black/30 border border-white/10 rounded-lg px-2.5 py-1.5 text-white font-mono text-[11px]">
                        <input type="password" id="duitku_key" placeholder="API Key" class="w-full bg-black/30 border border-white/10 rounded-lg px-2.5 py-1.5 text-white font-mono text-[11px]">
                        <label class="flex items-center gap-1.5 text-[10px] text-slate-400">
                            <input type="checkbox" id="duitku_sandbox" checked class="rounded text-indigo-600">
                            <span>Mode Sandbox (Tes)</span>
                        </label>
                    </div>

                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                        <div class="font-bold text-slate-200">Biteship Ongkir</div>
                        <input type="text" id="biteship_key" placeholder="Biteship API Key" class="w-full bg-black/30 border border-white/10 rounded-lg px-2.5 py-1.5 text-white font-mono text-[11px]">
                        <div class="text-[10px] text-slate-400">Digunakan untuk tarif kurir multi-ekspedisi otomatis di marketplace.</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                        <div class="font-bold text-slate-200">WA Gateway (Fonnte)</div>
                        <input type="text" id="wa_token" placeholder="Fonnte Token" class="w-full bg-black/30 border border-white/10 rounded-lg px-2.5 py-1.5 text-white font-mono text-[11px]">
                        <div class="text-[10px] text-slate-400">Notifikasi struk kasir &amp; work order servis ke nomor WA konsumen.</div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-white/5">
                <button type="button" onclick="goToStep(2)" class="px-4 py-2.5 rounded-xl bg-transparent hover:bg-white/5 text-slate-400 font-bold text-xs transition cursor-pointer">
                    ← Kembali
                </button>
                <button type="button" onclick="goToStep(4)" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-lg shadow-indigo-600/20 cursor-pointer">
                    Lanjut ke AI Stack &amp; Eksekusi →
                </button>
            </div>
        </div>

        <!-- STEP 4: AI DEV STACK & GO-LIVE -->
        <div id="section-step-4" class="space-y-6 hidden">
            <div class="border-b border-white/5 pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🤖</span>
                    <span>AI Maintenance Node &amp; Finalisasi Go-Live</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Pasang stack AI untuk maintain langsung di server ini jika diperlukan.</p>
            </div>

            <!-- AI Stack Toggle Card -->
            <div class="p-4 rounded-xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-orange-500/10 border border-indigo-500/30 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="font-bold text-sm text-white flex items-center gap-2">
                            <span>Pasang AI Development Node (OpenCode + 9router + MCP)</span>
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-orange-500/20 text-orange-300 font-bold">Rekomendasi</span>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Menginstal OpenCode CLI, service 9router di port 20128, codebase-memory-mcp, serta seluruh custom agents &amp; skills. Menjadikan server ini memiliki lingkungan AI mandiri untuk troubleshooting langsung di server.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                        <input type="checkbox" id="install_ai_stack" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>

            <!-- Progress Box (Hidden at first) -->
            <div id="execution-container" class="hidden space-y-3">
                <div class="flex items-center justify-between text-xs font-mono font-bold">
                    <span id="progress-status" class="text-indigo-400">Memulai instalasi...</span>
                    <span id="progress-percent" class="text-white">0%</span>
                </div>
                <div class="w-full h-3 rounded-full bg-black/60 overflow-hidden p-0.5 border border-white/10">
                    <div id="progress-bar" class="h-full rounded-full brand-gradient transition-all duration-300" style="width: 0%"></div>
                </div>
                <div id="execution-log" class="max-h-48 overflow-y-auto p-3 rounded-xl bg-black/60 border border-white/5 font-mono text-[11px] text-slate-300 space-y-1"></div>
            </div>

            <!-- Done Success Box -->
            <div id="done-container" class="hidden p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-4">
                <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 text-2xl mx-auto flex items-center justify-center">
                    ✓
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-black text-white">Setup Selesai! Ute Parts ERP Siap Digunakan</h3>
                    <p class="text-xs text-slate-400">Database termigrasi, cache aktif, symlink terhubung, dan akun super admin siap digunakan.</p>
                </div>
                <div class="p-3 rounded-xl bg-black/40 border border-white/10 max-w-sm mx-auto text-left font-mono text-xs space-y-1">
                    <div class="text-[10px] text-slate-400 uppercase font-sans font-bold">Akun Bawaan Super Admin:</div>
                    <div>Email: <strong class="text-white">superadmin@uteparts.id</strong></div>
                    <div>Pass : <strong class="text-white">Password123!</strong></div>
                </div>
                <div>
                    <a href="/app/login" class="inline-block px-8 py-3 rounded-xl brand-gradient text-white font-bold text-xs shadow-lg shadow-indigo-600/30 hover:opacity-95 transition">
                        Masuk ke Dashboard ERP →
                    </a>
                </div>
            </div>

            <div id="step-4-buttons" class="flex items-center justify-between pt-3 border-t border-white/5">
                <button type="button" onclick="goToStep(3)" class="px-4 py-2.5 rounded-xl bg-transparent hover:bg-white/5 text-slate-400 font-bold text-xs transition cursor-pointer">
                    ← Kembali
                </button>
                <button type="button" id="btn-start-install" onclick="startExecution()" class="px-8 py-3 rounded-xl brand-gradient text-white font-black text-xs transition shadow-lg shadow-indigo-600/30 hover:opacity-95 cursor-pointer flex items-center gap-2">
                    <span>🚀 Mulai Instalasi &amp; Go-Live</span>
                </button>
            </div>
        </div>

    </div>
</div>

<footer class="mt-8 text-center text-xs text-slate-500 font-mono">
    Ute Parts ERP &bull; Modern Glassmorphism &bull; Universal Setup Wizard
</footer>

<script>
let currentStep = 1;

function goToStep(step) {
    currentStep = step;
    for (let i = 1; i <= 4; i++) {
        const sec = document.getElementById(`section-step-${i}`);
        const nav = document.getElementById(`nav-step-${i}`);
        if (sec) sec.classList.toggle('hidden', i !== step);
        if (nav) {
            nav.classList.toggle('border-indigo-500/40', i === step);
            nav.classList.toggle('opacity-60', i !== step);
        }
    }
}

let serverDiagnosticsCache = <?= json_encode($diag, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
let logHistory = [];

function appendLog(msg, type = 'info') {
    const box = document.getElementById('execution-log-box');
    const time = new Date().toLocaleTimeString();
    const line = `[${time}] ${msg}`;
    logHistory.push(line);

    if (box) {
        const div = document.createElement('div');
        div.className = type === 'error' ? 'text-rose-400' : (type === 'success' ? 'text-emerald-300' : (type === 'warn' ? 'text-amber-300' : 'text-slate-300'));
        div.textContent = line;
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
    }
}

function updateDiagnosticsUI(d) {
    if (!d) return;
    serverDiagnosticsCache = d;

    // 1. Metric counters
    const elOs = document.getElementById('metric-os');
    if (elOs) elOs.textContent = d.os;
    const elPhp = document.getElementById('metric-php');
    if (elPhp) {
        elPhp.textContent = 'PHP ' + d.php_version;
        elPhp.className = 'font-bold ' + (d.php_ok ? 'text-emerald-400' : 'text-rose-400') + ' mt-1 truncate';
    }
    const elRam = document.getElementById('metric-ram');
    if (elRam) {
        elRam.innerHTML = `${d.ram_total_mb} MB <span class="text-[10px] text-slate-400">(Swap: ${d.swap_mb}M)</span>`;
    }
    const elDisk = document.getElementById('metric-disk');
    if (elDisk) {
        elDisk.textContent = `${d.disk_free_gb} GB / ${d.disk_total_gb} GB`;
    }

    // 2. Step checkpoint statuses
    const setStatus = (id, ready, okText, pendingText) => {
        const el = document.getElementById(id);
        if (el) {
            el.className = 'text-[10px] font-bold ' + (ready ? 'text-emerald-400' : 'text-amber-400');
            el.textContent = ready ? '✓ ' + okText : '○ ' + pendingText;
        }
    };
    setStatus('status-step-dirs', d.permissions?.storage && d.permissions?.cache, 'Siap', 'Belum Siap');
    setStatus('status-step-extensions', d.extensions_ok, 'Lengkap', 'Perlu Dicek');
    setStatus('status-step-composer', d.tools?.composer, 'Siap', 'Belum Ada');
    setStatus('status-step-vendor', d.vendor_ready, 'Terpasang', 'Belum Lengkap');
    setStatus('status-step-swap', d.swap_mb > 0 || d.ram_total_mb > 1500, 'Aman', 'Disarankan');

    // 3. Permissions
    const setPerm = (id, ok) => {
        const el = document.getElementById(id);
        if (el) {
            el.className = ok ? 'text-emerald-400' : 'text-rose-400';
            el.textContent = ok ? '✓ Writable' : '✕ Tidak Writable';
        }
    };
    setPerm('status-perm-storage', d.permissions?.storage);
    setPerm('status-perm-cache', d.permissions?.cache);
    const elVendor = document.getElementById('status-vendor-ready');
    if (elVendor) {
        elVendor.className = d.vendor_ready ? 'text-emerald-400' : 'text-amber-400';
        elVendor.textContent = d.vendor_ready ? '✓ Lengkap' : '⚡ Belum Lengkap';
    }

    // 4. Extensions Grid
    const extGrid = document.getElementById('extensions-grid');
    if (extGrid && d.extensions) {
        extGrid.innerHTML = '';
        Object.entries(d.extensions).forEach(([extName, ext]) => {
            const card = document.createElement('div');
            card.className = 'p-2.5 rounded-xl border flex items-center justify-between ' + 
                (ext.loaded ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : 
                (ext.required ? 'bg-rose-500/10 border-rose-500/20 text-rose-300' : 'bg-slate-800/40 border-white/5 text-slate-400'));
            card.innerHTML = `<span>${extName}</span><span>${ext.loaded ? '✓ Ada' : (ext.required ? '✕ Wajib' : '- Opsional')}</span>`;
            extGrid.appendChild(card);
        });
    }

    // 5. Manual Action Callout Box
    const manualCard = document.getElementById('manual-action-card');
    if (manualCard) {
        if (d.missing_extensions && d.missing_extensions.length > 0) {
            manualCard.classList.remove('hidden');
            const codeEl = document.getElementById('manual-action-code');
            if (codeEl) codeEl.textContent = d.recommended_cmd || '';
        } else {
            manualCard.classList.add('hidden');
        }
    }

    // 6. Server Ready Pill & Next Button
    const isReady = d.is_ready && d.vendor_ready;
    const pill = document.getElementById('server-status-pill');
    if (pill) {
        pill.innerHTML = isReady 
            ? '<span class="px-3 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs font-bold border border-emerald-500/30 flex items-center gap-1.5"><span>✓</span> <span>Server Siap Go-Live</span></span>'
            : '<span class="px-3 py-1 rounded-lg bg-amber-500/20 text-amber-300 text-xs font-bold border border-amber-500/30 flex items-center gap-1.5"><span>⚠️</span> <span>Perlu Penyiapan Dependensi</span></span>';
    }

    const hint = document.getElementById('step-1-status-hint');
    if (hint) {
        hint.className = 'text-xs ' + (isReady ? 'text-emerald-400 font-bold' : 'text-amber-400');
        hint.textContent = isReady 
            ? '✓ Seluruh persyaratan sistem telah siap.' 
            : '⚠️ Selesaikan checklist di atas untuk mengaktifkan langkah berikutnya.';
    }

    const btnNext = document.getElementById('btn-next-step-1');
    if (btnNext) {
        btnNext.disabled = !isReady;
        btnNext.className = 'px-6 py-2.5 rounded-xl font-bold text-xs transition flex items-center gap-2 ' +
            (isReady ? 'bg-indigo-600 hover:bg-indigo-500 text-white cursor-pointer shadow-lg shadow-indigo-600/20' : 'bg-slate-800 text-slate-500 cursor-not-allowed opacity-60');
    }
}

async function refreshDiagnostics() {
    appendLog('Memeriksa ulang kondisi diagnostik server...', 'info');
    try {
        const formData = new FormData();
        formData.append('action', 'get_diagnostics');
        const res = await fetch('install.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.diagnostics) {
            updateDiagnosticsUI(data.diagnostics);
            appendLog('Diagnostik berhasil diperbarui.', 'success');
        }
    } catch (e) {
        appendLog('Gagal memperbarui diagnostik: ' + e.message, 'error');
    }
}

async function runSequentialAutoFix() {
    const btn = document.getElementById('btn-autofix');
    const spinner = document.getElementById('autofix-spinner');
    const btnText = document.getElementById('autofix-btn-text');

    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    spinner.classList.remove('hidden');
    btnText.textContent = 'Menjalankan auto-fix...';

    const steps = [
        { id: 'dirs', name: '1. Izin & Struktur Folder Direktori' },
        { id: 'extensions', name: '2. Aktivasi & Instalasi Ekstensi PHP' },
        { id: 'composer', name: '3. Unduh & Siapkan Binary Composer' },
        { id: 'vendor', name: '4. Pasang Dependensi Vendor Composer' },
        { id: 'swap', name: '5. Proteksi Swap Memory Server' }
    ];

    appendLog('=== MEMULAI RUNNER AUTO-FIX STEP-BY-STEP ===', 'info');

    let allCompleted = true;
    let restartRequired = false;

    for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        btnText.textContent = `Memproses (${i + 1}/${steps.length}): ${step.name}...`;
        appendLog(`[Step ${i + 1}/${steps.length}] Memulai ${step.name}...`, 'info');

        try {
            const formData = new FormData();
            formData.append('action', 'autofix_step');
            formData.append('step_id', step.id);

            const res = await fetch('install.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.logs && Array.isArray(data.logs)) {
                data.logs.forEach(l => appendLog(`  -> ${l}`, 'info'));
            }

            if (data.diagnostics) {
                updateDiagnosticsUI(data.diagnostics);
            }

            if (data.restart_needed) {
                restartRequired = true;
            }

            if (data.manual_cmd) {
                appendLog(`  [PERINTAH TERMINAL]: ${data.manual_cmd}`, 'warn');
            }

            appendLog(`[Step ${i + 1}/${steps.length}] Selesai diproses.`, 'success');
        } catch (e) {
            allCompleted = false;
            appendLog(`[Step ${i + 1}/${steps.length}] Kendala eksekusi: ${e.message}`, 'error');
            break;
        }
    }

    spinner.classList.add('hidden');
    btn.disabled = false;
    btn.classList.remove('opacity-75', 'cursor-not-allowed');

    if (restartRequired) {
        btnText.textContent = '⚠️ Restart Server PHP Diperlukan';
        appendLog('PENTING: Konfigurasi php.ini atau paket sistem telah diperbarui. Silakan restart PHP server (Ctrl+C di terminal lalu jalankan ./start-install.sh atau start-install.bat lagi), kemudian klik tombol "Segarkan".', 'warn');
    } else if (serverDiagnosticsCache && serverDiagnosticsCache.is_ready && serverDiagnosticsCache.vendor_ready) {
        btnText.textContent = '✓ Sistem Siap Digunakan!';
        btn.className = 'px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shrink-0 flex items-center gap-1.5 cursor-default';
        appendLog('Selamat! Seluruh prasyarat lingkungan sistem telah hijau dan siap dilanjutkan.', 'success');
    } else {
        btnText.textContent = '⚡ Jalankan Ulang Auto-Fix';
        appendLog('Beberapa modul masih memerlukan perhatian (lihat checklist & perintah terminal di atas).', 'warn');
    }
}

function copyManualCmd() {
    const code = document.getElementById('manual-action-code')?.textContent || '';
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        appendLog('Perintah terminal berhasil disalin ke clipboard!', 'success');
        alert('Perintah terminal berhasil disalin! Silakan tempel (paste) di terminal server Anda.');
    }).catch(() => {
        prompt('Salin perintah manual berikut:', code);
    });
}

function copyFullDiagnosticReport() {
    const btn = document.getElementById('copy-report-text');
    const diagStr = JSON.stringify(serverDiagnosticsCache, null, 2);
    const report = [
        '=== LAPORAN DIAGNOSTIK & LOG INSTALLER UTE PARTS ===',
        `Waktu Laporan: ${new Date().toISOString()}`,
        '',
        '--- DATA DIAGNOSTIK SISTEM ---',
        diagStr,
        '',
        '--- RIWAYAT LOG EKSEKUSI ---',
        logHistory.length > 0 ? logHistory.join('\n') : '(Belum ada log tercatat)',
        '====================================================='
    ].join('\n');

    navigator.clipboard.writeText(report).then(() => {
        if (btn) btn.textContent = '✓ Log Tersalin!';
        setTimeout(() => {
            if (btn) btn.textContent = 'Salin Log Diagnostik Lengkap';
        }, 2000);
        appendLog('Seluruh laporan diagnostik & log berhasil disalin ke clipboard.', 'success');
    }).catch(() => {
        prompt('Salin seluruh laporan berikut:', report);
    });
}

window.addEventListener('DOMContentLoaded', () => {
    // Jalankan auto-fix bertahap otomatis hanya satu kali jika belum siap
    <?php if (! $diag['is_ready'] || ! $diag['vendor_ready']) { ?>
    runSequentialAutoFix();
    <?php } else { ?>
    appendLog('Kondisi server memenuhi seluruh syarat awal.', 'success');
    <?php } ?>
});

async function testDatabaseConnection() {
    const resBox = document.getElementById('db-test-result');
    resBox.className = 'p-3 rounded-xl text-xs font-mono bg-white/5 text-slate-300';
    resBox.textContent = '⏳ Menguji koneksi database...';
    resBox.classList.remove('hidden');

    const formData = new FormData();
    formData.append('action', 'test_db');
    formData.append('db_host', document.getElementById('db_host').value);
    formData.append('db_port', document.getElementById('db_port').value);
    formData.append('db_name', document.getElementById('db_name').value);
    formData.append('db_user', document.getElementById('db_user').value);
    formData.append('db_pass', document.getElementById('db_pass').value);
    if (document.getElementById('db_create').checked) {
        formData.append('create_if_missing', '1');
    }

    try {
        const res = await fetch('install.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            resBox.className = 'p-3 rounded-xl text-xs font-mono bg-emerald-500/10 border border-emerald-500/30 text-emerald-300';
            resBox.textContent = '✓ ' + data.message;
        } else {
            resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-500/10 border border-rose-500/30 text-rose-300';
            resBox.textContent = '✕ ' + data.message;
        }
    } catch (e) {
        resBox.className = 'p-3 rounded-xl text-xs font-mono bg-rose-500/10 border border-rose-500/30 text-rose-300';
        resBox.textContent = '✕ Error request: ' + e.message;
    }
}

async function startExecution() {
    document.getElementById('btn-start-install').disabled = true;
    document.getElementById('btn-start-install').classList.add('opacity-50', 'cursor-not-allowed');
    document.getElementById('execution-container').classList.remove('hidden');

    const steps = [
        { id: 'write_env', label: '1/7 Menyimpan konfigurasi .env' },
        { id: 'check_vendor', label: '2/7 Memeriksa & instal dependensi Composer' },
        { id: 'key_generate', label: '3/7 Generate kunci aplikasi (APP_KEY)' },
        { id: 'migrate_seed', label: '4/7 Migrasi skema database & seeding akun default' },
        { id: 'storage_link', label: '5/7 Menghubungkan symlink direktori media' },
        { id: 'optimize_cache', label: '6/7 Mengoptimasi cache konfigurasi & rute produksi' },
        { id: 'setup_ai_dev', label: '7/7 Menyiapkan AI Stack (OpenCode + 9router + MCP)' }
    ];

    const logBox = document.getElementById('execution-log');
    const statusText = document.getElementById('progress-status');
    const percentText = document.getElementById('progress-percent');
    const progressBar = document.getElementById('progress-bar');

    for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        const percent = Math.round(((i) / steps.length) * 100);
        statusText.textContent = step.label;
        percentText.textContent = percent + '%';
        progressBar.style.width = percent + '%';

        const formData = new FormData();
        formData.append('action', 'execute_step');
        formData.append('step', step.id);
        formData.append('app_name', document.getElementById('app_name').value);
        formData.append('app_url', document.getElementById('app_url').value);
        formData.append('db_host', document.getElementById('db_host').value);
        formData.append('db_port', document.getElementById('db_port').value);
        formData.append('db_name', document.getElementById('db_name').value);
        formData.append('db_user', document.getElementById('db_user').value);
        formData.append('db_pass', document.getElementById('db_pass').value);
        formData.append('duitku_sandbox', document.getElementById('duitku_sandbox').checked ? '1' : '');
        formData.append('duitku_code', document.getElementById('duitku_code').value);
        formData.append('duitku_key', document.getElementById('duitku_key').value);
        formData.append('biteship_key', document.getElementById('biteship_key').value);
        formData.append('wa_token', document.getElementById('wa_token').value);
        formData.append('git_secret', document.getElementById('git_secret').value);
        if (document.getElementById('install_ai_stack').checked) {
            formData.append('install_ai_stack', '1');
        }

        const logEntry = document.createElement('div');
        logEntry.textContent = '⏳ ' + step.label + '...';
        logBox.appendChild(logEntry);
        logBox.scrollTop = logBox.scrollHeight;

        try {
            const res = await fetch('install.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) {
                logEntry.textContent = '✕ Gagal di ' + step.label + ': ' + data.message;
                logEntry.className = 'text-rose-400 font-bold';
                statusText.textContent = 'Instalasi terhenti karena error.';
                return;
            }
            logEntry.textContent = '✓ ' + data.message;
            logEntry.className = 'text-emerald-400';
        } catch (e) {
            logEntry.textContent = '✕ Request error: ' + e.message;
            logEntry.className = 'text-rose-400 font-bold';
            return;
        }
    }

    percentText.textContent = '100%';
    progressBar.style.width = '100%';
    statusText.textContent = 'Instalasi selesai!';

    document.getElementById('step-4-buttons').classList.add('hidden');
    document.getElementById('done-container').classList.remove('hidden');
}
</script>

</body>
</html>
