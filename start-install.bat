@echo off
setlocal enabledelayedexpansion
title Ute Parts ERP - Universal One-Click Launcher (Port 1741)

echo ======================================================================
echo    Ute Parts ERP ^& Marketplace - Universal Local Launcher
echo    Port Default: 1741 (Akses Lokal ^& Jaringan Wi-Fi/LAN/HP/iOS)
echo ======================================================================
echo.

cd /d "%~dp0"

:: 1. Deteksi PHP Binary (System PATH, Tools Lokal, Laragon, XAMPP)
set "PHP_BIN="

where php >nul 2>nul
if %errorlevel% equ 0 (
    set "PHP_BIN=php"
) else (
    if exist "%~dp0tools\php\php.exe" (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    ) else if exist "C:\laragon\bin\php\php-8.4*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.4*) do set "PHP_BIN=%%D\php.exe"
    ) else if exist "C:\laragon\bin\php\php-8.3*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.3*) do set "PHP_BIN=%%D\php.exe"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else if exist "C:\php\php.exe" (
        set "PHP_BIN=C:\php\php.exe"
    )
)

:: 2. Auto-Install PHP Portable jika perangkat kosongan (Zero-Dependency)
if "%PHP_BIN%"=="" (
    echo [INFO] PHP tidak ditemukan di sistem Windows ini.
    echo [INFO] Menjalankan pengunduhan otomatis PHP 8.3 Portable resmi Windows...
    
    if not exist "%~dp0tools" mkdir "%~dp0tools"
    
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ErrorActionPreference = 'Stop';" ^
        "Write-Host 'Mengunduh PHP Portable 8.3 dari windows.php.net...';" ^
        "$zipPath = Join-Path $pwd 'tools\php.zip';" ^
        "$destDir = Join-Path $pwd 'tools\php';" ^
        "$urls = @(" ^
        "  'https://windows.php.net/downloads/releases/php-8.3.17-Win32-vs16-x64.zip'," ^
        "  'https://windows.php.net/downloads/releases/archives/php-8.3.16-Win32-vs16-x64.zip'," ^
        "  'https://windows.php.net/downloads/releases/archives/php-8.3.14-Win32-vs16-x64.zip'" ^
        ");" ^
        "$downloaded = $false;" ^
        "foreach ($u in $urls) {" ^
        "  try {" ^
        "    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12;" ^
        "    Invoke-WebRequest -Uri $u -OutFile $zipPath -TimeoutSec 60;" ^
        "    if ((Get-Item $zipPath).Length -gt 1000000) { $downloaded = $true; break }" ^
        "  } catch {}" ^
        "};" ^
        "if ($downloaded) {" ^
        "  Write-Host 'Mengekstrak PHP...';" ^
        "  Expand-Archive -Path $zipPath -DestinationPath $destDir -Force;" ^
        "  Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue;" ^
        "  $iniDev = Join-Path $destDir 'php.ini-development';" ^
        "  $iniTarget = Join-Path $destDir 'php.ini';" ^
        "  if (Test-Path $iniDev) {" ^
        "    $c = Get-Content $iniDev -Raw;" ^
        "    $c = $c -replace ';extension_dir = \"ext\"', 'extension_dir = \"ext\"';" ^
        "    $c = $c -replace ';extension=curl', 'extension=curl';" ^
        "    $c = $c -replace ';extension=fileinfo', 'extension=fileinfo';" ^
        "    $c = $c -replace ';extension=gd', 'extension=gd';" ^
        "    $c = $c -replace ';extension=mbstring', 'extension=mbstring';" ^
        "    $c = $c -replace ';extension=openssl', 'extension=openssl';" ^
        "    $c = $c -replace ';extension=pdo_mysql', 'extension=pdo_mysql';" ^
        "    $c = $c -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite';" ^
        "    $c = $c -replace ';extension=sqlite3', 'extension=sqlite3';" ^
        "    $c = $c -replace ';extension=intl', 'extension=intl';" ^
        "    $c = $c -replace ';extension=zip', 'extension=zip';" ^
        "    $c = $c -replace ';extension=bcmath', 'extension=bcmath';" ^
        "    Set-Content -Path $iniTarget -Value $c;" ^
        "  }" ^
        "  Write-Host 'PHP Portable 8.3 siap digunakan.';" ^
        "} else {" ^
        "  Write-Warning 'Gagal mengunduh otomatis. Silakan pasang Laragon atau XAMPP.';" ^
        "}"
        
    if exist "%~dp0tools\php\php.exe" (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    )
)

if "%PHP_BIN%"=="" (
    echo [ERROR] PHP tidak berhasil dipasang otomatis.
    echo Silakan install PHP ^>= 8.3 atau pasang Laragon/XAMPP lalu jalankan kembali file ini.
    echo.
    pause
    exit /b 1
)

echo [OK] PHP terdeteksi: %PHP_BIN%

:: 3. Auto-Fix Direktori Storage, Database & Cache
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" 2>nul
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" 2>nul
if not exist "storage\framework\views" mkdir "storage\framework\views" 2>nul
if not exist "storage\logs" mkdir "storage\logs" 2>nul
if not exist "storage\app\public" mkdir "storage\app\public" 2>nul
if not exist "bootstrap\cache" mkdir "bootstrap\cache" 2>nul
if not exist "database" mkdir "database" 2>nul

:: 4. Deteksi & Auto-Download Composer
set "COMPOSER_CMD="
where composer >nul 2>nul
if %errorlevel% equ 0 (
    set "COMPOSER_CMD=composer"
) else (
    if not exist "composer.phar" (
        echo [INFO] Mengunduh composer.phar resmi via PowerShell...
        powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri https://getcomposer.org/composer-stable.phar -OutFile composer.phar" 2>nul
    )
    if exist "composer.phar" (
        set "COMPOSER_CMD="%PHP_BIN%" composer.phar"
    )
)

:: 5. Auto-Fix Dependensi Vendor
if not exist "vendor\autoload.php" (
    if not "%COMPOSER_CMD%"=="" (
        echo [INFO] Menjalankan composer install untuk melengkapi dependensi vendor...
        %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader
        if not exist "vendor\autoload.php" (
            echo [WARN] Fallback composer install dengan toleransi platform requirements...
            %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter
        )
        if not exist "vendor\autoload.php" (
            %COMPOSER_CMD% install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs
        )
    ) else (
        echo [WARN] Composer belum terpasang. Dependensi dapat dilengkapi via GUI Installer.
    )
)

:: 6. Konfigurasi Port 1741 (Unik ^& Jarang Dipakai)
set "PORT=1741"
netstat -ano | findstr /r /c:":%PORT% " >nul 2>nul
if not errorlevel 1 (
    echo [WARN] Port 1741 sedang terpakai, mencari port alternatif...
    for /l %%P in (1742,1,1760) do (
        netstat -ano | findstr /r /c:":%%P " >nul 2>nul
        if errorlevel 1 (
            set "PORT=%%P"
            goto :PORT_FOUND
        )
    )
)
:PORT_FOUND

:: 7. Deteksi IP Jaringan Lokal (IPv4 LAN/Wi-Fi)
set "LAN_IP="
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    if not defined LAN_IP (
        set "RAW_IP=%%a"
        set "LAN_IP=!RAW_IP: =!"
    )
)

:: Cek & sesuaikan bind-address MySQL di Laragon / XAMPP agar bisa diakses LAN secara otomatis
powershell -Command "Get-ChildItem -Path 'C:\laragon\bin\mysql\*\my.ini', 'C:\xampp\mysql\bin\my.ini' -ErrorAction SilentlyContinue | ForEach-Object { (Get-Content $_.FullName) -replace 'bind-address\s*=\s*127\.0\.0\.1', 'bind-address = 0.0.0.0' | Set-Content $_.FullName }" >nul 2>nul

:: Opsional: Daftarkan port di Windows Defender Firewall agar bisa diakses perangkat lain (Port 1741 & 3306)
netsh advfirewall firewall add rule name="Ute Parts ERP (Port %PORT%)" dir=in action=allow protocol=TCP localport=%PORT% >nul 2>nul
netsh advfirewall firewall add rule name="Ute Parts MySQL LAN (Port 3306)" dir=in action=allow protocol=TCP localport=3306 >nul 2>nul

set "LOCAL_URL=http://localhost:%PORT%/install.php"
set "NETWORK_URL="
if not "%LAN_IP%"=="" (
    set "NETWORK_URL=http://%LAN_IP%:%PORT%/install.php"
)

echo [OK] Port aktif: %PORT%
echo.
echo ======================================================================
echo    Server Ute Parts Berhasil Dijalankan!
echo    - Dari Komputer Ini       : %LOCAL_URL%
if not "%NETWORK_URL%"=="" (
echo    - Dari HP / iPhone / iPad : %NETWORK_URL%
)
echo ----------------------------------------------------------------------
echo    Petunjuk Multi-Client Satu Database:
echo    - Komputer Pusat / Server : Pilih 'Mode Server' di Web Installer.
echo    - Komputer Kasir / Client : Pilih 'Mode Client' -> 'Auto-Scan Server'.
echo ======================================================================
echo    Tutup jendela ini jika ingin mematikan server.
echo.

:: 8. Buka Browser Otomatis
start "" "%LOCAL_URL%"

:: 9. Jalankan Built-in Web Server (0.0.0.0 agar bisa diakses dari LAN)
"%PHP_BIN%" -S 0.0.0.0:%PORT% -t public
pause
