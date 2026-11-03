@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>nul
title Bremspace ERP & POS System - System Updater
cd /d "%~dp0"

echo ======================================================================
echo    Bremspace ERP & POS System — Pembaruan Sistem Otomatis (Updater)
echo ======================================================================
echo.

set "PHP_BIN=php"
where php >nul 2>nul
if errorlevel 1 (
    if exist "%~dp0tools\php\php.exe" (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    )
)

echo [INFO] Memeriksa lisensi & pembaruan sistem...

:: 1. Jika berjalan dengan Docker
if exist "%~dp0compose.yaml" (
    where docker >nul 2>nul
    if not errorlevel 1 (
        echo [INFO] Mengunduh pembaruan kontainer Docker Bremspace...
        docker compose pull
        docker compose up -d
        docker compose exec -T app php artisan migrate --force
        docker compose exec -T app php artisan optimize:clear
        echo [OK] Kontainer Docker berhasil diperbarui ke versi terbaru!
        echo.
        echo ======================================================================
        echo Pembaruan sistem selesai. Tekan tombol apa saja untuk keluar...
        pause >nul
        exit /b 0
    )
)

"%PHP_BIN%" -r ^
"require_once 'scripts/updater/SystemUpdater.php'; ^
use Scripts\Updater\SystemUpdater; ^
$status = SystemUpdater::checkForUpdates(); ^
echo 'Status: ' . $status['message'] . PHP_EOL; ^
if (!$status['has_update']) { ^
    echo '[OK] Sistem sudah menggunakan versi terbaru.' . PHP_EOL; ^
    exit(0); ^
} ^
echo '[INFO] Mengunduh & menerapkan pembaruan...' . PHP_EOL; ^
$res = SystemUpdater::executeUpdate(); ^
if ($res['success']) { ^
    echo '[OK] Pembaruan berhasil diterapkan!' . PHP_EOL; ^
} else { ^
    echo '[ERROR] Gagal: ' . $res['log'] . PHP_EOL; ^
    exit(1); ^
}"

echo.
echo ======================================================================
echo Pembaruan sistem selesai. Tekan tombol apa saja untuk keluar...
pause >nul
