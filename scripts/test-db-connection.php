<?php

// scripts/test-db-connection.php
// Helper CLI untuk menguji koneksi MySQL secara akurat, cepat dan aman

error_reporting(0);
ini_set('display_errors', '0');

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 3306);
$user = $argv[3] ?? 'root';
$pass = $argv[4] ?? '';
$dbName = $argv[5] ?? '';

// Bersihkan trailing carriage return / newline
$pass = trim($pass, "\r\n");

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

// 1. Coba kredensial input
$res1 = testMysql($host, $user, $pass, $port);
if ($res1['ok']) {
    $link = $res1['link'];
    if ($dbName !== '') {
        $cleanDb = str_replace('`', '``', $dbName);
        @mysqli_query($link, "CREATE DATABASE IF NOT EXISTS `{$cleanDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    @mysqli_close($link);
    echo "SUCCESS|{$pass}\n";
    exit(0);
}

// 2. Jika input pass tidak kosong dan user root, coba password kosong (XAMPP/Laragon default)
if ($pass !== '' && $user === 'root') {
    $res2 = testMysql($host, $user, '', $port);
    if ($res2['ok']) {
        $link = $res2['link'];
        if ($dbName !== '') {
            $cleanDb = str_replace('`', '``', $dbName);
            @mysqli_query($link, "CREATE DATABASE IF NOT EXISTS `{$cleanDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        @mysqli_close($link);
        echo "SUCCESS_EMPTY_PASS|\n";
        exit(0);
    }
}

// 3. Laporkan kegagalan
echo "ERROR|{$res1['errno']}|{$res1['error']}\n";
exit(1);
