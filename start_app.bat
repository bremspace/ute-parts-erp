@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
title ERP ^& POS System - Start Application (Port 1741)

echo ======================================================================
echo    ERP ^& POS System - Application Launcher
echo    Memulai server ^& mempersiapkan seluruh layanan sistem...
echo ======================================================================
echo.

cd /d "%~dp0"

:: 1. Deteksi PHP Binary (Wajib PHP >= 8.4)
set "PHP_BIN="

if exist "%~dp0tools\php\php.exe" (
    "%~dp0tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%~dp0tools\php\php.exe"
        goto :PHP_READY
    )
)

if exist "C:\laragon\bin\php\php-8.5*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.5*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_READY
        )
    )
)

if exist "C:\laragon\bin\php\php-8.4*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.4*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_READY
        )
    )
)

where php >nul 2>nul
if not errorlevel 1 (
    php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=php"
        goto :PHP_READY
    )
)

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=C:\xampp\php\php.exe"
        goto :PHP_READY
    )
)

:: Auto-Download PHP Portable jika belum ada sama sekali
echo [INFO] Menyiapkan PHP portable di folder tools...
if not exist "%~dp0tools" mkdir "%~dp0tools" 2>nul
if exist "%~dp0scripts\setup-windows-php.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-windows-php.ps1"
)
if exist "%~dp0tools\php\php.exe" (
    "%~dp0tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    )
)

:PHP_READY
if "%PHP_BIN%"=="" (
    echo [ERROR] PHP minimal 8.4 belum terpasang atau gagal dieksekusi.
    echo Jalankan start-install.bat untuk memeriksa instalasi sistem.
    pause
    exit /b 1
)

echo [OK] PHP: %PHP_BIN%

:: 2. Persiapan Direktori Storage, Database & Cache
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" 2>nul
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" 2>nul
if not exist "storage\framework\views" mkdir "storage\framework\views" 2>nul
if not exist "storage\logs" mkdir "storage\logs" 2>nul
if not exist "storage\app\public" mkdir "storage\app\public" 2>nul
if not exist "bootstrap\cache" mkdir "bootstrap\cache" 2>nul
if not exist "database" mkdir "database" 2>nul

:: 3. Pastikan Symlink Storage Aktif
if not exist "public\storage" (
    "%PHP_BIN%" artisan storage:link >nul 2>nul
)

:: 4. Cek & Aktifkan MySQL Lokal jika port 3306 belum aktif
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if errorlevel 1 (
    net start MySQL >nul 2>nul
    net start MySQL80 >nul 2>nul
    net start MariaDB >nul 2>nul
    for %%P in ("C:\laragon\bin\mysql" "D:\laragon\bin\mysql" "E:\laragon\bin\mysql") do (
        if exist "%%~P" (
            for /d %%D in ("%%~P\*") do (
                if exist "%%~D\bin\mysqld.exe" (
                    start "" /b "%%~D\bin\mysqld.exe" --defaults-file="%%~D\my.ini" --standalone >nul 2>nul
                )
            )
        )
    )
    for %%X in ("C:\xampp\mysql\bin\mysqld.exe" "D:\xampp\mysql\bin\mysqld.exe" "E:\xampp\mysql\bin\mysqld.exe") do (
        if exist "%%~X" (
            start "" /b "%%~X" --standalone >nul 2>nul
        )
    )
)

:: 5. Konfigurasi Port Server (Default: 1741)
set "PORT=1741"
set "SERVER_ALREADY_RUNNING=0"
netstat -ano | findstr /r /c:":%PORT% " >nul 2>nul
if not errorlevel 1 (
    set "SERVER_ALREADY_RUNNING=1"
)

:: 6. Nyalakan Web Server di Background jika belum aktif
if "%SERVER_ALREADY_RUNNING%"=="0" (
    echo [INFO] Menyalakan Web Server di port %PORT%...
    wscript.exe "%~dp0scripts\run-server-background.vbs" "%PHP_BIN%" "%PORT%" "%~dp0public"
    timeout /t 1 /nobreak >nul 2>nul
    echo [OK] Web Server aktif di background (Port %PORT%).
) else (
    echo [OK] Web Server sudah aktif di port %PORT%.
)

:: 7. Nyalakan Background Queue Worker
tasklist | findstr /i "php.exe" | findstr /i "artisan" >nul 2>nul
if errorlevel 1 (
    if exist "%~dp0.env" (
        echo [INFO] Menyalakan Background Queue Worker...
        wscript.exe "%~dp0scripts\run-command-background.vbs" "\"%PHP_BIN%\" \"%~dp0artisan\" queue:work --sleep=3 --tries=3"
        echo [OK] Queue Worker aktif di background.
    )
) else (
    echo [OK] Queue Worker siap.
)

:: 8. Deteksi IP Jaringan Lokal (IPv4 LAN/Wi-Fi)
set "LAN_IP="
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    if not defined LAN_IP (
        set "RAW_IP=%%a"
        set "LAN_IP=!RAW_IP: =!"
    )
)

:: 9. Tentukan Rute Tujuan (Default: /app/login jika terpasang, /install.php jika belum)
set "TARGET_PATH=/app/login"
if not exist "%~dp0storage\installed.lock" (
    if not exist "%~dp0.env" (
        set "TARGET_PATH=/install.php"
    )
)

set "LOCAL_URL=http://localhost:%PORT%!TARGET_PATH!"
set "NETWORK_URL="
if not "%LAN_IP%"=="" (
    set "NETWORK_URL=http://%LAN_IP%:%PORT%!TARGET_PATH!"
)

set "ONLINE_URL="
if exist "%~dp0.env" (
    for /f "tokens=1,2 delims==" %%A in ('findstr /b "APP_URL=" "%~dp0.env"') do (
        set "RAW_APP_URL=%%B"
        set "RAW_APP_URL=!RAW_APP_URL:"=!"
        echo !RAW_APP_URL! | findstr /v "localhost 127.0.0.1" >nul 2>nul
        if not errorlevel 1 (
            set "ONLINE_URL=!RAW_APP_URL!!TARGET_PATH!"
        )
    )
)

:: 10. Buka Browser Otomatis
start "" "%LOCAL_URL%"

echo.
echo ======================================================================
echo    Aplikasi ERP ^& POS Berhasil Berjalan!
echo    - Komputer Ini (Lokal)     : %LOCAL_URL%
if not "%NETWORK_URL%"=="" (
echo    - Dari HP/Tablet/Kasir LAN : %NETWORK_URL%
)
if not "!ONLINE_URL!"=="" (
echo    - Akses Internet (Online)  : !ONLINE_URL!
)
echo ----------------------------------------------------------------------
echo    Status Layanan:
echo    [OK] Web Server   : Port %PORT% (Aktif di Background)
echo    [OK] Antrean/Job  : Database Queue Worker (Aktif di Background)
echo ----------------------------------------------------------------------
echo    [INFO] Server tetap berjalan di background walau CMD ini ditutup.
echo    [INFO] Untuk mematikan server kapan saja, jalankan: stop-server.bat
echo ======================================================================
echo.
timeout /t 5 >nul
exit /b 0
