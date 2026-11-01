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
:: Wajib PHP >= 8.4 (Rekomendasi PHP 8.5+) untuk performa maksimal
set "PHP_BIN="

:: Periksa apakah tools\php lokal sudah ada dan versinya >= 8.4
if exist "%~dp0tools\php\php.exe" (
    "%~dp0tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    ) else (
        echo [INFO] PHP portable di tools\php terdeteksi versi lama (^< 8.4). Menyiapkan pembaruan ke PHP 8.5...
        rd /s /q "%~dp0tools\php" 2>nul
    )
)

:: Periksa Laragon PHP 8.5 lalu 8.4
if "!PHP_BIN!"=="" (
    if exist "C:\laragon\bin\php\php-8.5*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.5*) do (
            if "!PHP_BIN!"=="" set "PHP_BIN=%%D\php.exe"
        )
    )
)
if "!PHP_BIN!"=="" (
    if exist "C:\laragon\bin\php\php-8.4*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.4*) do (
            if "!PHP_BIN!"=="" set "PHP_BIN=%%D\php.exe"
        )
    )
)

:: Periksa system PATH php
if "!PHP_BIN!"=="" (
    where php >nul 2>nul
    if %errorlevel% equ 0 (
        php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
        if not errorlevel 1 (
            set "PHP_BIN=php"
        ) else (
            echo [WARN] PHP di system PATH versinya ^< 8.4. Laravel 13 memerlukan PHP ^>= 8.4 (disarankan 8.5+).
        )
    )
)

:: Periksa XAMPP / path standar lain
if "!PHP_BIN!"=="" (
    if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
        if not errorlevel 1 set "PHP_BIN=C:\xampp\php\php.exe"
    )
)

:: 2. Auto-Install Visual C++ Redistributable & PHP 8.5 Portable jika belum ada
if "!PHP_BIN!"=="" (
    echo [INFO] PHP ^>= 8.4/8.5 belum terpasang. Menjalankan penyiapan otomatis zero-dependency...
    
    if not exist "%~dp0tools" mkdir "%~dp0tools"
    
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ErrorActionPreference = 'Stop';" ^
        "Write-Host 'Memeriksa Microsoft Visual C++ 2015-2022 Redistributable (vcredist)...';" ^
        "$hasVc = (Test-Path \"$env:SystemRoot\System32\vcruntime140.dll\") -and (Test-Path \"$env:SystemRoot\System32\msvcp140.dll\");" ^
        "if (-not $hasVc) {" ^
        "  Write-Host 'Mengunduh & memasang Microsoft Visual C++ Redistributable resmi...';" ^
        "  $vcUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe';" ^
        "  $vcFile = Join-Path $pwd 'tools\vc_redist.x64.exe';" ^
        "  try {" ^
        "    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12;" ^
        "    Invoke-WebRequest -Uri $vcUrl -OutFile $vcFile -TimeoutSec 60;" ^
        "    Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -Wait;" ^
        "    Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue;" ^
        "    Write-Host 'Microsoft Visual C++ Redistributable berhasil dipasang.';" ^
        "  } catch {" ^
        "    Write-Warning ('Gagal instal vcredist otomatis: ' + $_.Exception.Message);" ^
        "  }" ^
        "};" ^
        "Write-Host 'Mengunduh PHP Portable 8.5 resmi dari windows.php.net...';" ^
        "$zipPath = Join-Path $pwd 'tools\php.zip';" ^
        "$destDir = Join-Path $pwd 'tools\php';" ^
        "$urls = @(" ^
        "  'https://windows.php.net/downloads/releases/php-8.5.12-1-Win32-vs17-x64.zip'," ^
        "  'https://windows.php.net/downloads/releases/php-8.4.27-1-Win32-vs17-x64.zip'," ^
        "  'https://windows.php.net/downloads/releases/archives/php-8.4.27-1-Win32-vs17-x64.zip'" ^
        ");" ^
        "$downloaded = $false;" ^
        "foreach ($u in $urls) {" ^
        "  try {" ^
        "    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12;" ^
        "    Invoke-WebRequest -Uri $u -OutFile $zipPath -TimeoutSec 90;" ^
        "    if ((Get-Item $zipPath).Length -gt 1000000) { $downloaded = $true; break }" ^
        "  } catch {}" ^
        "};" ^
        "if ($downloaded) {" ^
        "  Write-Host 'Mengekstrak PHP 8.5...';" ^
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
        "  Write-Host 'PHP Portable 8.5 siap digunakan.';" ^
        "} else {" ^
        "  Write-Warning 'Gagal mengunduh otomatis. Silakan pasang Laragon (PHP 8.5/8.4) atau XAMPP.';" ^
        "}"
        
    if exist "%~dp0tools\php\php.exe" (
        set "PHP_BIN=%~dp0tools\php\php.exe"
    )
)

if "%PHP_BIN%"=="" (
    echo [ERROR] PHP ^>= 8.4 tidak berhasil disiapkan secara otomatis.
    echo Silakan install PHP ^>= 8.4 atau pasang Laragon dengan PHP 8.4 lalu jalankan kembali file ini.
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
