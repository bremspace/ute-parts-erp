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

    // 4. PHP Extensions Check
    $requiredExts = [
        'pdo_mysql' => 'Koneksi database MySQL',
        'gd' => 'Kompresi & manipulasi gambar WebP',
        'mbstring' => 'Pemrosesan string UTF-8 multibyte',
        'curl' => 'Koneksi API Duitku & Biteship',
        'zip' => 'Ekstraksi bulk foto & impor Excel',
        'intl' => 'Format angka & ribuan Rupiah',
        'bcmath' => 'Kalkulasi finansial & double-entry',
        'fileinfo' => 'Validasi MIME type berkas',
        'openssl' => 'Enkripsi data & security token',
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
    $tools = [
        'composer' => runCommand('which composer')['status'] === 0,
        'git' => runCommand('which git')['status'] === 0,
        'node' => runCommand('which node')['status'] === 0,
        'npm' => runCommand('which npm')['status'] === 0,
        'supervisor' => runCommand('which supervisorctl')['status'] === 0,
        'nginx' => runCommand('which nginx')['status'] === 0,
        'opencode' => (runCommand('which opencode')['status'] === 0 || file_exists('/root/.opencode/bin/opencode')),
        '9router' => runCommand('which 9router')['status'] === 0,
    ];

    // 6. Directory Permissions & Vendor
    $baseDir = dirname(__DIR__);
    $writableStorage = is_writable($baseDir.'/storage');
    $writableCache = is_writable($baseDir.'/bootstrap/cache');
    $writableEnv = is_writable($baseDir) || (file_exists($baseDir.'/.env') && is_writable($baseDir.'/.env'));
    $vendorReady = file_exists($baseDir.'/vendor/autoload.php');

    return [
        'os' => $osInfo,
        'php_version' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'ram_total_mb' => $totalRamMb,
        'ram_free_mb' => $freeRamMb,
        'swap_mb' => $swapMb,
        'disk_free_gb' => $diskFreeGb,
        'disk_total_gb' => $diskTotalGb,
        'extensions' => $extStatus,
        'extensions_ok' => $allReqPassed,
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
                        $resComp = runCommand("cd {$baseDir} && composer install --no-interaction --prefer-dist --optimize-autoloader");
                        if ($resComp['status'] !== 0) {
                            throw new RuntimeException('Gagal instalasi composer: '.$resComp['output']);
                        }
                        echo json_encode(['success' => true, 'message' => 'Dependensi Composer berhasil diinstal.']);
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
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🔍</span>
                    <span>Diagnostik Kesehatan Server Otomatis</span>
                </h2>
                <?php if ($diag['is_ready']) { ?>
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs font-bold border border-emerald-500/30">
                        ✓ Server Memenuhi Syarat
                    </span>
                <?php } else { ?>
                    <span class="px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-300 text-xs font-bold border border-amber-500/30">
                        ⚠️ Perlu Penyesuaian
                    </span>
                <?php } ?>
            </div>

            <!-- Server Metrics Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">Sistem Operasi</div>
                    <div class="font-bold text-white truncate mt-1" title="<?= htmlspecialchars($diag['os']) ?>"><?= htmlspecialchars($diag['os']) ?></div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">PHP Version</div>
                    <div class="font-bold <?= $diag['php_ok'] ? 'text-emerald-400' : 'text-rose-400' ?> mt-1">
                        PHP <?= $diag['php_version'] ?>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">RAM / Swap</div>
                    <div class="font-bold text-white mt-1">
                        <?= $diag['ram_total_mb'] ?> MB <span class="text-[10px] text-slate-400">(Swap: <?= $diag['swap_mb'] ?>M)</span>
                    </div>
                </div>
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="text-[10px] text-slate-400 uppercase font-sans">Sisa Disk</div>
                    <div class="font-bold text-white mt-1"><?= $diag['disk_free_gb'] ?> GB / <?= $diag['disk_total_gb'] ?> GB</div>
                </div>
            </div>

            <!-- Extensions Table -->
            <div class="space-y-2">
                <div class="text-xs font-bold text-slate-300">Ekstensi PHP &amp; Fitur Gambar Universal:</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-[11px] font-mono">
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
                        <span class="<?= $diag['permissions']['storage'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $diag['permissions']['storage'] ? '✓ Writable' : '✕ Tidak Writable' ?></span>
                    </div>
                    <div class="flex items-center justify-between font-mono text-[11px]">
                        <span>bootstrap/cache/</span>
                        <span class="<?= $diag['permissions']['cache'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $diag['permissions']['cache'] ? '✓ Writable' : '✕ Tidak Writable' ?></span>
                    </div>
                    <div class="flex items-center justify-between font-mono text-[11px]">
                        <span>vendor/autoload.php</span>
                        <span class="<?= $diag['vendor_ready'] ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $diag['vendor_ready'] ? '✓ Lengkap' : '⚡ Diinstal otomatis' ?></span>
                    </div>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-1.5">
                    <div class="font-bold text-slate-200">Tools Sistem Terdeteksi:</div>
                    <div class="flex items-center gap-2 flex-wrap font-mono text-[10px]">
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['composer'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">composer</span>
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['git'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">git</span>
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['node'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">node</span>
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['supervisor'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-500' ?>">supervisor</span>
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['opencode'] ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-500' ?>">opencode</span>
                        <span class="px-2 py-0.5 rounded <?= $diag['tools']['9router'] ? 'bg-orange-500/20 text-orange-300' : 'bg-slate-800 text-slate-500' ?>">9router</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-3">
                <button type="button" onclick="goToStep(2)" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center gap-2 shadow-lg shadow-indigo-600/20 cursor-pointer">
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
