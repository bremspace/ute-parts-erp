#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * QA Auto-Runner — universal (Windows / Linux / macOS)
 *
 * Satu perintah untuk menjalankan seluruh pipeline QA:
 *   cek PHP version → deteksi RAM → install dependency bila perlu →
 *   clear cache → Pint → PHPStan → Deptrac → Composer Audit
 *
 * Dibuat berbasis PHP (bukan bash) agar identik di semua operating system.
 * Windows tidak memerlukan bash, Git Bash, atau WSL.
 *
 * Cara pakai:
 *   php scripts/qa-auto.php
 *   composer qa-auto
 *
 * Output log di qa-results/:
 *   qa-<timestamp>.log           output lengkap human-readable
 *   qa-<timestamp>.json          data terstruktur (siap di-parse AI)
 *   qa-<timestamp>.summary.txt   ringkasan status + Next Actions for AI
 */

// ─── Konfigurasi ────────────────────────────────────────────────────────────

/** Versi PHP minimum (mengikuti composer.json: require.php ^8.3) */
const REQUIRED_PHP_MAJOR = 8;
const REQUIRED_PHP_MINOR = 3;

$projectRoot = dirname(__DIR__);

if (! is_dir($projectRoot) || ! is_file($projectRoot.'/composer.json')) {
    fwrite(STDERR, "Gagal: script harus dijalankan dari dalam folder project (composer.json tidak ditemukan).\n");
    exit(1);
}

chdir($projectRoot);

$logDir = $projectRoot.'/qa-results';
if (! is_dir($logDir) && ! mkdir($logDir, 0o775, true) && ! is_dir($logDir)) {
    fwrite(STDERR, "Gagal: tidak bisa membuat folder qa-results.\n");
    exit(1);
}

$timestamp = date('Ymd-His');
$logFile = $logDir."/qa-{$timestamp}.log";
$jsonLogFile = $logDir."/qa-{$timestamp}.json";
$summaryFile = $logDir."/qa-{$timestamp}.summary.txt";

/** @var list<array{name:string,status:string,duration_ms:int,output:string,details:string}> $steps */
$steps = [];
$overallStatus = 'success';

// ─── Helper: logging ─────────────────────────────────────────────────────────

$logHandle = fopen($logFile, 'wb');
if ($logHandle === false) {
    fwrite(STDERR, "Gagal: tidak bisa menulis file log {$logFile}.\n");
    exit(1);
}

/** Tulis ke layar dan ke file log dengan newline Unix (\n) di semua OS. */
function out(string $message, $logHandle): void
{
    $line = $message."\n";
    echo $line;
    fwrite($logHandle, $line);
}

function logAt(string $message, $logHandle): void
{
    out('['.date('H:i:s').'] '.$message, $logHandle);
}

function logSection(string $title, $logHandle): void
{
    out('', $logHandle);
    out('=== '.$title.' ===', $logHandle);
}

/** @param resource $logHandle */

// ─── Helper: menjalankan command ─────────────────────────────────────────────

/**
 * Jalankan satu command dan kembalikan [exitCode, combinedOutput].
 *
 * Command diberikan sebagai array (bukan string) supaya PHP tidak memakai shell.
 * Ini penting untuk cross-platform: di Windows array diproses oleh CreateProcess,
 * di Unix oleh execve — tidak ada perbedaan quoting seperti bila memakai
 * `sh -c` atau `bash -c`.
 *
 * @param  list<string>  $command
 * @param  array<string,string>|null  $env
 * @return array{int,string}
 */
function runCommand(array $command, ?array $env = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, null, $env);
    if (! is_resource($process)) {
        return [127, 'Gagal menjalankan: '.implode(' ', $command)];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $open = [1 => $pipes[1], 2 => $pipes[2]];

    // Baca stream secara bergilir supaya tidak menumpuk di memori dan tidak deadlock.
    while ($open !== []) {
        $read = array_values($open);
        $write = null;
        $except = null;

        if (@stream_select($read, $write, $except, 1) === false) {
            break;
        }

        foreach ($read as $stream) {
            $chunk = fread($stream, 8192);
            $key = array_search($stream, $open, true);
            if ($chunk === false || $chunk === '') {
                if (feof($stream)) {
                    fclose($stream);
                    unset($open[$key]);
                }

                continue;
            }
            if ($key === 1) {
                $stdout .= $chunk;
            } else {
                $stderr .= $chunk;
            }
        }
    }

    foreach ($open as $stream) {
        fclose($stream);
    }

    $exitCode = proc_close($process);

    return [$exitCode, $stdout.$stderr];
}

/** Environment Composer: izinkan plugin saat dijalankan sebagai root/superuser. */
function composerEnv(): array
{
    $env = getenv();
    $env['COMPOSER_ALLOW_SUPERUSER'] = '1';

    return $env;
}

/** Path binary tool QA (portable, tidak perlu ./ di Windows). */
function toolPath(string $name): string
{
    global $projectRoot;

    $direct = $projectRoot.'/vendor/bin/'.$name;

    if (is_file($direct)) {
        return $direct;
    }

    // Windows: vendor/bin/*.bat
    $bat = $projectRoot.'/vendor/bin/'.$name.'.bat';
    if (is_file($bat)) {
        return $bat;
    }

    return $direct;
}

// ─── Helper: step runner ─────────────────────────────────────────────────────

/**
 * Jalankan satu step QA, catat hasilnya, dan LANJUT ke step berikutnya
 * meskipun step ini gagal (tidak short-circuit di tengah pipeline).
 *
 * @param  list<string>  $command
 * @param  resource  $logHandle
 * @param  callable(string):bool|null  $outputValidator  return false untuk memaksa status failed
 * @param  callable(string):bool|null  $inconclusiveTest  return true bila step tidak bisa dijalankan (mis. jaringan)
 */
function runStep(
    string $name,
    array $command,
    $logHandle,
    ?callable $outputValidator = null,
    ?callable $inconclusiveTest = null
): void {
    global $steps, $overallStatus;

    logSection('STEP: '.$name, $logHandle);

    $startMs = (int) round(microtime(true) * 1000);
    [$exitCode, $output] = runCommand($command);
    $durationMs = (int) round(microtime(true) * 1000) - $startMs;

    if ($inconclusiveTest !== null && $inconclusiveTest($output)) {
        // Contoh: composer audit tidak bisa menghubungi packagist.org.
        // Ini BUKAN tanda ada vulnerability, hanya langkah yang tidak tervalidasi.
        $status = 'inconclusive';
    } else {
        $status = $exitCode === 0 ? 'success' : 'failed';
        if ($status === 'success' && $outputValidator !== null) {
            $status = $outputValidator($output) ? 'success' : 'failed';
        }
    }

    if ($status !== 'success') {
        $overallStatus = 'failed';
    }

    $outputLines = preg_split('/\R/u', $output) ?: [];
    $details = trim(implode("\n", array_slice($outputLines, -5)));
    $jsonOutput = implode("\n", array_slice($outputLines, 0, 200));

    $steps[] = [
        'name' => $name,
        'status' => $status,
        'duration_ms' => $durationMs,
        'output' => $jsonOutput,
        'details' => $details,
    ];

    logAt('  Status: '.$status.' ('.$durationMs.'ms)', $logHandle);
    out($output, $logHandle);
}

// ─── Helper: deteksi RAM ────────────────────────────────────────────────────

/**
 * Total RAM dalam MB.
 *
 * Prioritas:
 *   1. Env SYSTEM_TOTAL_MEMORY_MB (override manual, berguna di Windows/macOS)
 *   2. /proc/meminfo (Linux, dibaca langsung — tidak memanggil `free`)
 *   3. null bila tidak terdeteksi
 */
function detectTotalRamMb(): ?int
{
    $override = getenv('SYSTEM_TOTAL_MEMORY_MB');
    if ($override !== false && is_numeric($override) && (int) $override > 0) {
        return (int) $override;
    }

    $memInfo = '/proc/meminfo';
    if (is_readable($memInfo)) {
        $content = file_get_contents($memInfo);
        if ($content !== false && preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $content, $m) === 1) {
            return (int) round(((int) $m[1]) / 1024);
        }
    }

    return null;
}

/**
 * Pilih profil analisis berdasarkan RAM.
 * Rendah RAM → PHPStan serial + memory kecil supaya tidak OOM.
 *
 * @return array{profile:string, memory:string, parallel:bool, processes:int}
 */
function selectProfile(?int $ramMb): array
{
    if ($ramMb === null) {
        // Asumsi konservatif bila RAM tidak terdeteksi.
        return ['profile' => 'lowram', 'memory' => '256M', 'parallel' => false, 'processes' => 1];
    }

    if ($ramMb < 1024) {
        return ['profile' => 'lowram', 'memory' => '256M', 'parallel' => false, 'processes' => 1];
    }

    if ($ramMb < 2048) {
        return ['profile' => 'normal', 'memory' => '512M', 'parallel' => true, 'processes' => 2];
    }

    return ['profile' => 'highram', 'memory' => '1G', 'parallel' => true, 'processes' => 4];
}

// ─── MulaiEksekusi ───────────────────────────────────────────────────────────

logAt('QA Auto-Runner started', $logHandle);
logAt('  Project: '.$projectRoot, $logHandle);
logAt('  OS: '.PHP_OS_FAMILY.' ('.PHP_OS.')', $logHandle);
logAt('  Log dir: '.$logDir, $logHandle);

// 1. Cek versi PHP
logSection('CHECK: PHP Version', $logHandle);
$phpVersion = PHP_VERSION;
$phpMajor = PHP_MAJOR_VERSION;
$phpMinor = PHP_MINOR_VERSION;
logAt('  Detected: PHP '.$phpVersion, $logHandle);

if ($phpMajor < REQUIRED_PHP_MAJOR || ($phpMajor === REQUIRED_PHP_MAJOR && $phpMinor < REQUIRED_PHP_MINOR)) {
    logAt('  GAGAL: PHP >= '.REQUIRED_PHP_MAJOR.'.'.REQUIRED_PHP_MINOR.' required, got '.$phpVersion, $logHandle);
    fclose($logHandle);
    exit(1);
}
logAt('  OK: PHP version >= '.REQUIRED_PHP_MAJOR.'.'.REQUIRED_PHP_MINOR, $logHandle);

// 2. Deteksi RAM dan pilih profil
logSection('CHECK: System RAM', $logHandle);
$totalRamMb = detectTotalRamMb();
if ($totalRamMb === null) {
    logAt('  RAM tidak terdeteksi otomatis — memakai profil paling aman (lowram).', $logHandle);
    logAt('  Override manual: set env SYSTEM_TOTAL_MEMORY_MB=<jumlah MB>.', $logHandle);
} else {
    logAt('  Total RAM: '.$totalRamMb.' MB', $logHandle);
}

$profile = selectProfile($totalRamMb);
logAt(
    '  Profil terpilih: '.$profile['profile']
    .' (PHPStan: '.$profile['memory'].', '.$profile['processes'].' proses'
    .($profile['parallel'] ? ', --parallel' : ', serial').')',
    $logHandle
);

// 3. Cek Composer dan dependency
logSection('CHECK: Composer & Dependencies', $logHandle);

$composerVersionRun = runCommand(['composer', '--version', '--no-ansi'], composerEnv());
$composerVersion = trim($composerVersionRun[1]);
if ($composerVersionRun[0] !== 0) {
    logAt('  GAGAL: Composer tidak ditemukan. Pastikan Composer 2 terinstall dan ada di PATH.', $logHandle);
    fclose($logHandle);
    exit(1);
}
logAt('  Composer: '.$composerVersion, $logHandle);

$autoload = $projectRoot.'/vendor/autoload.php';
if (! is_file($autoload)) {
    logAt('  Dependencies belum terinstall — menjalankan composer install...', $logHandle);
    runStep(
        'composer-install',
        ['composer', 'install', '--prefer-dist', '--no-progress', '--no-interaction'],
        $logHandle
    );
} else {
    logAt('  OK: vendor/autoload.php tersedia', $logHandle);
}

// 4. Verifikasi binary tool QA
logSection('CHECK: QA Tool Binaries', $logHandle);
foreach (['pint', 'phpstan', 'deptrac'] as $tool) {
    if (is_file(toolPath($tool))) {
        logAt('  OK: vendor/bin/'.$tool.' ditemukan', $logHandle);
    } else {
        logAt('  PERINGATAN: vendor/bin/'.$tool.' tidak ditemukan — jalankan composer install', $logHandle);
    }
}

// 5. Clear cache Laravel
logSection('PREP: Clear Laravel Caches', $logHandle);
runStep('cache-clear', [
    PHP_BINARY, 'artisan', 'config:clear',
    '--no-ansi',
], $logHandle);
runStep('route-clear', [PHP_BINARY, 'artisan', 'route:clear', '--no-ansi'], $logHandle);
runStep('view-clear', [PHP_BINARY, 'artisan', 'view:clear', '--no-ansi'], $logHandle);

// 6. Pipeline QA
logSection('QA PIPELINE START (profil: '.$profile['profile'].')', $logHandle);

// 6a. Pint — code style
runStep('pint-format-check', [toolPath('pint'), '--test'], $logHandle);

// 6b. PHPStan — static analysis
//     PHPStan 2.x memakai --parallel (bukan --processes).
$phpstanCommand = [
    toolPath('phpstan'), 'analyse',
    '--no-progress',
    '--memory-limit='.$profile['memory'],
];
if ($profile['parallel']) {
    $phpstanCommand[] = '--parallel';
}
runStep('phpstan', $phpstanCommand, $logHandle);

// 6c. Deptrac — arsitektur dependensi
runStep('deptrac', [toolPath('deptrac'), 'analyse', '--no-progress'], $logHandle);

// 6d. Composer audit — kerentanan dependency.
//     Composer keluar dengan kode 0 walau menemukan advisory, jadi hasil
//     output harus diperiksa: kalimat "No security vulnerability advisories
//     found" berarti bersih.
//     Composer audit butuh akses jaringan ke packagist.org. Bila jaringan
//     gagal, hasilnya ditandai "inconclusive" — BUKAN vulnerability.
runStep(
    'composer-audit',
    ['composer', 'audit', '--no-interaction'],
    $logHandle,
    static fn (string $output): bool => stripos($output, 'No security vulnerability advisories found') !== false,
    static function (string $output): bool {
        foreach (['curl error', 'Connection timed out', 'Could not connect', 'network is unreachable', 'Failed to connect'] as $needle) {
            if (stripos($output, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
);

// 7. Tulis JSON log
$report = [
    'timestamp' => $timestamp,
    'hostname' => gethostname() !== false ? gethostname() : 'unknown',
    'os' => PHP_OS_FAMILY,
    'project_root' => $projectRoot,
    'php_version' => $phpVersion,
    'ram_mb' => $totalRamMb,
    'profile' => $profile['profile'],
    'phpstan_memory' => $profile['memory'],
    'phpstan_parallel' => $profile['parallel'],
    'phpstan_processes' => $profile['processes'],
    'overall_status' => $overallStatus,
    'steps' => $steps,
];

$jsonEncoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($jsonEncoded === false) {
    logAt('  PERINGATAN: gagal encode JSON log: '.json_last_error_msg(), $logHandle);
} else {
    file_put_contents($jsonLogFile, $jsonEncoded."\n");
}

// 8. Tulis summary
$summary = [];
$summary[] = 'QA Auto-Runner Summary';
$summary[] = '======================';
$summary[] = 'Timestamp: '.$timestamp;
$summary[] = 'Hostname: '.($report['hostname']);
$summary[] = 'OS: '.PHP_OS_FAMILY;
$summary[] = 'Project: '.$projectRoot;
$summary[] = 'PHP Version: '.$phpVersion;
$summary[] = 'Total RAM: '.($totalRamMb ?? 'tidak terdeteksi').($totalRamMb !== null ? ' MB' : '');
$summary[] = 'Profil Used: '.$profile['profile'];
$summary[] = '  - PHPStan Memory: '.$profile['memory'];
$summary[] = '  - PHPStan Parallel: '.($profile['parallel'] ? 'ya' : 'tidak (serial)');
$summary[] = '  - PHPStan Processes: '.$profile['processes'];
$summary[] = '';
$summary[] = 'Overall Status: '.$overallStatus;
$summary[] = '';
$summary[] = 'Log Files:';
$summary[] = '  - Human log: '.$logFile;
$summary[] = '  - JSON log:  '.$jsonLogFile;
$summary[] = '  - Summary:   '.$summaryFile;
$summary[] = '';
$summary[] = 'Steps Status:';

foreach ($steps as $step) {
    $summary[] = sprintf('  %-20s %-8s (%dms)', $step['name'], $step['status'], $step['duration_ms']);
}

$summary[] = '';
$summary[] = 'AI Consumption Guide:';
$summary[] = '  - Parse '.$jsonLogFile.' untuk hasil terstruktur';
$summary[] = '  - Field steps[].output memuat maksimal 200 baris pertama output tool';
$summary[] = '  - Output lengkap ada di '.$logFile;
$summary[] = '';
$summary[] = 'Next Actions for AI:';

$failed = array_values(array_filter($steps, static fn (array $s): bool => $s['status'] === 'failed'));
$inconclusive = array_values(array_filter($steps, static fn (array $s): bool => $s['status'] === 'inconclusive'));

if ($failed === [] && $inconclusive === []) {
    $summary[] = '  (semua step lulus, tidak ada yang perlu diperbaiki)';
} else {
    foreach ($failed as $step) {
        $summary[] = '  - FIX: '.$step['name'].' — '.$step['details'];
    }
    foreach ($inconclusive as $step) {
        $summary[] = '  - RETRY: '.$step['name'].' — step tidak tervalidasi (kemungkinan masalah jaringan, bukan bug)';
    }
}

// Informasi baseline PHPStan bila ada.
$phpstanNeon = $projectRoot.'/phpstan.neon';
$baselineFile = $projectRoot.'/phpstan-baseline.neon';
if (is_file($phpstanNeon)
    && is_file($baselineFile)
    && str_contains((string) file_get_contents($phpstanNeon), 'phpstan-baseline.neon')
) {
    $baselineContent = (string) file_get_contents($baselineFile);
    $baselineCount = substr_count($baselineContent, 'identifier:');
    $summary[] = '';
    $summary[] = 'PHPStan Baseline: '.$baselineCount.' error di-baseline (diabaikan, hanya error baru yang dilaporkan)';
}

file_put_contents($summaryFile, implode("\n", $summary)."\n");

// 9. Laporan akhir
logSection('DONE', $logHandle);
logAt('Summary: '.$summaryFile, $logHandle);
logAt('Full log: '.$logFile, $logHandle);
logAt('JSON log: '.$jsonLogFile, $logHandle);
logAt('Overall: '.$overallStatus, $logHandle);
logAt('Cara baca untuk AI: kirimkan file '.$summaryFile.' atau '.$jsonLogFile.' ke AI untuk rhs perbaikan.', $logHandle);

fclose($logHandle);
echo "\n";
echo (string) file_get_contents($summaryFile);

exit($overallStatus === 'success' ? 0 : 1);
