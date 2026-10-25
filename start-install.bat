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

:: 2. Auto-Fix Direktori Storage & Cache
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" 2>nul
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" 2>nul
if not exist "storage\framework\views" mkdir "storage\framework\views" 2>nul
if not exist "storage\logs" mkdir "storage\logs" 2>nul
if not exist "storage\app\public" mkdir "storage\app\public" 2>nul
if not exist "bootstrap\cache" mkdir "bootstrap\cache" 2>nul

:: 3. Deteksi & Auto-Download Composer
set "COMPOSER_CMD="
where composer >nul 2>nul
if %errorlevel% equ 0 (
    set "COMPOSER_CMD=composer"
) else (
    if not exist "composer.phar" (
        echo [INFO] Mengunduh composer.phar otomatis via PowerShell...
        powershell -Command "Invoke-WebRequest -Uri https://getcomposer.org/composer-stable.phar -OutFile composer.phar" 2>nul
    )
    if exist "composer.phar" (
        set "COMPOSER_CMD="%PHP_BIN%" composer.phar"
    )
)

:: 4. Auto-Fix Dependensi Vendor
if not exist "vendor\autoload.php" (
    if not "%COMPOSER_CMD%"=="" (
        echo [INFO] Menjalankan composer install untuk melengkapi dependensi vendor...
        %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader
        if not exist "vendor\autoload.php" (
            echo [WARN] Menjalankan fallback composer install dengan toleransi ekstensi platform...
            %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter
        )
        if not exist "vendor\autoload.php" (
            %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs
        )
    ) else (
        echo [WARN] Composer belum terpasang. Dependensi dapat dilengkapi via GUI Installer.
    )
)

:: 5. Cari port bebas (8000..8015)
set "PORT=8000"
for /l %%P in (8000,1,8015) do (
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

:: 6. Buka Browser
start "" "%URL%"

:: 7. Jalankan Built-in Web Server
echo ======================================================================
echo    Server berjalan di http://localhost:%PORT%
echo    Tutup jendela ini jika ingin mematikan server.
echo ======================================================================
echo.
"%PHP_BIN%" -S 0.0.0.0:%PORT% -t public
pause
