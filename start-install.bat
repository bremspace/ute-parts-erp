@echo off
setlocal enabledelayedexpansion
title Ute Parts ERP - One-Click Launcher

echo ======================================================================
echo    Ute Parts ERP ^& Marketplace - Universal Local Launcher
echo ======================================================================
echo.

cd /d "%~dp0"

:: 1. Deteksi PHP Binary (System PATH, Laragon, XAMPP, Local)
set "PHP_BIN="
where php >nul 2>nul
if %errorlevel% equ 0 (
    set "PHP_BIN=php"
) else (
    if exist "C:\laragon\bin\php\php-8.4*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.4*) do set "PHP_BIN=%%D\php.exe"
    ) else if exist "C:\laragon\bin\php\php-8.3*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.3*) do set "PHP_BIN=%%D\php.exe"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    )
)

if "%PHP_BIN%"=="" (
    echo [ERROR] PHP tidak ditemukan di sistem!
    echo Silakan install PHP ^>= 8.3, atau jalankan via Laragon/XAMPP.
    echo.
    pause
    exit /b 1
)

echo [OK] PHP terdeteksi: %PHP_BIN%

:: 2. Cek dependensi vendor/autoload.php, jalankan composer install jika ada composer
if not exist "vendor\autoload.php" (
    echo [INFO] Folder vendor belum lengkap. Memeriksa Composer...
    where composer >nul 2>nul
    if %errorlevel% equ 0 (
        echo [INFO] Menjalankan composer install...
        composer install --no-interaction --prefer-dist --optimize-autoloader
    ) else (
        echo [WARN] Composer tidak ditemukan di PATH. Penginstalan dependensi dapat diproses via GUI.
    )
)

:: 3. Cari port bebas (8000..8010)
set "PORT=8000"
for /l %%P in (8000,1,8010) do (
    netstat -ano | findstr /r /c:":%%P " >nul 2>nul
    if errorlevel 1 (
        set "PORT=%%P"
        goto :PORT_FOUND
    )
)
:PORT_FOUND

set "URL=http://localhost:%PORT%/install.php"
echo [OK] Port terpilih: %PORT%
echo [INFO] Membuka browser ke %URL% ...
echo.

:: 4. Buka Browser
start "" "%URL%"

:: 5. Jalankan Built-in Web Server
echo ======================================================================
echo    Server berjalan di http://localhost:%PORT%
echo    Tutup jendela ini jika ingin mematikan server.
echo ======================================================================
echo.
"%PHP_BIN%" -S 0.0.0.0:%PORT% -t public
pause
