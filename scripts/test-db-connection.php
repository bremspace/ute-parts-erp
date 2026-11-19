<?php

// scripts/test-db-connection.php
// Helper CLI untuk menguji koneksi MySQL secara akurat, cepat dan aman

error_reporting(0);
ini_set('display_errors', '0');

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Dukung argumen flag --host=, --port=, --user=, --pass=, --dbname= maupun posisi argumen
$opt = getopt('', ['host:', 'port:', 'user:', 'pass::', 'dbname:']);

$host = $opt['host'] ?? ($argv[1] ?? '127.0.0.1');
$port = (int) ($opt['port'] ?? ($argv[2] ?? 3306));
$user = $opt['user'] ?? ($argv[3] ?? 'root');
$pass = isset($opt['pass']) ? (string) $opt['pass'] : ($argv[4] ?? '');
$dbName = $opt['dbname'] ?? ($argv[5] ?? '');

// Jika flag --pass tanpa nilai, pass diset string kosong
if ($pass === false || $pass === null) {
    $pass = '';
}
$pass = trim((string) $pass, "\r\n");

function testMysql(string $host, string $user, string $pass, int $port): array
{
    $link = mysqli_init();
    if (! $link) {
        return ['ok' => false, 'errno' => 2002, 'error' => 'Gagal inisialisasi driver MySQL'];
    }
    mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $ok = @mysqli_real_connect($link, $host, $user, $pass, '', $port);
    if ($ok) {
        return ['ok' => true, 'link' => $link];
    }

    return [
        'ok' => false,
        'errno' => mysqli_connect_errno() ?: 2002,
        'error' => mysqli_connect_error() ?: 'Koneksi gagal',
    ];
}

// Susun daftar prioritas password yang diuji
$candidates = [];
$candidates[] = $pass;
if ($pass !== '') {
    $candidates[] = '';
}
if ($user === 'root') {
    foreach (['root', '123456', 'admin', 'password', 'mariadb', 'mysql'] as $common) {
        if (! in_array($common, $candidates, true)) {
            $candidates[] = $common;
        }
    }
}

$lastErr = ['errno' => 2002, 'error' => 'Koneksi gagal'];
$workingLink = null;
$workingPass = '';

foreach ($candidates as $cand) {
    $res = testMysql($host, $user, $cand, $port);
    if ($res['ok']) {
        $workingLink = $res['link'];
        $workingPass = $cand;
        break;
    }
    $lastErr = $res;
}

if ($workingLink !== null) {
    if ($dbName !== '') {
        $cleanDb = str_replace('`', '``', $dbName);
        @mysqli_query($workingLink, "CREATE DATABASE IF NOT EXISTS `{$cleanDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    @mysqli_close($workingLink);
    echo "SUCCESS|{$workingPass}\n";
    exit(0);
}

echo "ERROR|{$lastErr['errno']}|{$lastErr['error']}\n";
exit(1);
