@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
title ERP ^& POS System - Universal One-Click Launcher (Port 1741)

echo ======================================================================
echo    ERP ^& POS System - Universal Local Launcher
echo    Port Default: 1741 (Akses Lokal ^& Jaringan Wi-Fi/LAN/HP/iOS)
echo ======================================================================
echo.

cd /d "%~dp0"

:: 1. Deteksi PHP Binary
:: Wajib PHP >= 8.4 (Disarankan PHP 8.5+) untuk Laravel 13 & Symfony 8
set "PHP_BIN="

:: Cek tools\php lokal jika sudah ada
if exist "%~dp0tools\php\php.exe" (
    "%~dp0tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%~dp0tools\php\php.exe"
        goto :PHP_READY
    )
    echo [INFO] PHP di tools\php versi lama. Membersihkan untuk update ke PHP 8.5...
    rd /s /q "%~dp0tools\php" 2>nul
)

:: Cek Laragon PHP 8.5
if exist "C:\laragon\bin\php\php-8.5*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.5*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_READY
        )
    )
)

:: Cek Laragon PHP 8.4
if exist "C:\laragon\bin\php\php-8.4*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.4*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_READY
        )
    )
)

:: Cek PHP di System PATH
where php >nul 2>nul
if not errorlevel 1 (
    php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=php"
        goto :PHP_READY
    )
    echo [WARN] PHP di system PATH versinya kurang dari 8.4. Laravel 13 memerlukan PHP 8.4+.
)

:: Cek XAMPP PHP
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=C:\xampp\php\php.exe"
        goto :PHP_READY
    )
)

:: 2. Auto-Install Visual C++ & PHP 8.5 Portable (Zero-Dependency)
echo [INFO] PHP minimal 8.4/8.5 belum terpasang di sistem.
echo [INFO] Menjalankan penyiapan otomatis PHP 8.5 Portable...
if not exist "%~dp0tools" mkdir "%~dp0tools" 2>nul

if exist "%~dp0scripts\setup-windows-php.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-windows-php.ps1"
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $z = Join-Path $pwd 'tools\php.zip'; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.5.12-1-Win32-vs17-x64.zip' -OutFile $z -TimeoutSec 120; Expand-Archive -Path $z -DestinationPath (Join-Path $pwd 'tools\php') -Force; Remove-Item $z -Force -ErrorAction SilentlyContinue"
)

if exist "%~dp0tools\php\php.exe" (
    set "PHP_BIN=%~dp0tools\php\php.exe"
)

:PHP_READY
if "%PHP_BIN%"=="" (
    echo [ERROR] PHP minimal 8.4 tidak berhasil disiapkan secara otomatis.
    echo Pasang Laragon dengan PHP 8.4/8.5 atau unduh PHP 8.5 lalu jalankan kembali file ini.
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

:: 4. Deteksi & Siapkan Composer
set "COMPOSER_RUN="
where composer >nul 2>nul
if not errorlevel 1 (
    set "COMPOSER_RUN=composer"
) else (
    if not exist "composer.phar" (
        echo [INFO] Mengunduh composer.phar resmi via PowerShell...
        powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri https://getcomposer.org/composer-stable.phar -OutFile composer.phar" 2>nul
    )
    if exist "composer.phar" (
        set COMPOSER_RUN="%PHP_BIN%" composer.phar
    )
)

:: 5. Auto-Fix Dependensi Vendor
if not exist "vendor\autoload.php" (
    if not "%COMPOSER_RUN%"=="" (
        echo [INFO] Menjalankan composer install untuk melengkapi dependensi vendor...
        echo [INFO] Catatan Windows: 'Generating optimized autoload files' memindai 11.000+ file kelas.
        echo [INFO] Normal memakan waktu 1-3 menit karena scanning NTFS ^& antivirus. Mohon tunggu...
        %COMPOSER_RUN% install --no-interaction --prefer-dist --no-scripts
        if not exist "vendor\autoload.php" (
            echo [WARN] Fallback composer install dengan toleransi platform requirements...
            %COMPOSER_RUN% install --no-interaction --prefer-dist --no-scripts --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter
        )
        if not exist "vendor\autoload.php" (
            %COMPOSER_RUN% install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs
        )
        if exist "vendor\autoload.php" (
            echo [INFO] Mendaftarkan package Laravel...
            "%PHP_BIN%" artisan package:discover --ansi
        )
    ) else (
        echo [WARN] Composer belum terpasang. Dependensi dapat dilengkapi via GUI Installer.
    )
)

:: 6. Konfigurasi Port 1741 (Unik & Jarang Dipakai)
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

:: Cek & Otomatis Nyalakan Service MySQL lokal jika port 3306 belum aktif
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if errorlevel 1 (
    net start MySQL >nul 2>nul
    net start MySQL80 >nul 2>nul
    net start MariaDB >nul 2>nul
    for %%P in ("C:\laragon\bin\mysql" "D:\laragon\bin\mysql" "E:\laragon\bin\mysql") do (
        if exist "%%~P" (
            for /d %%D in ("%%~P\*") do (
                if exist "%%~D\bin\mysqld.exe" (
                    echo [INFO] Menyalakan service MySQL lokal Laragon di background...
                    start "" /b "%%~D\bin\mysqld.exe" --defaults-file="%%~D\my.ini" --standalone >nul 2>nul
                )
            )
        )
    )
    for %%X in ("C:\xampp\mysql\bin\mysqld.exe" "D:\xampp\mysql\bin\mysqld.exe" "E:\xampp\mysql\bin\mysqld.exe") do (
        if exist "%%~X" (
            echo [INFO] Menyalakan service MySQL lokal XAMPP di background...
            start "" /b "%%~X" --standalone >nul 2>nul
        )
    )
)

:: Buka port web server di Windows Defender Firewall (Port %PORT%)
netsh advfirewall firewall add rule name="ERP POS System (Port %PORT%)" dir=in action=allow protocol=TCP localport=%PORT% >nul 2>nul

:: Periksa apakah MySQL port 3306 aktif
set "MYSQL_ACTIVE=0"
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if not errorlevel 1 (
    set "MYSQL_ACTIVE=1"
)

set "TARGET_PATH=/install.php"
if exist "%~dp0storage\installed.lock" (
    if "%MYSQL_ACTIVE%"=="1" (
        set "TARGET_PATH=/app/login"
    ) else if exist "%~dp0.env" (
        findstr /m /c:"DB_CONNECTION=sqlite" "%~dp0.env" >nul 2>nul
        if not errorlevel 1 (
            set "TARGET_PATH=/app/login"
        )
    )
)

if "%MYSQL_ACTIVE%"=="0" (
    echo [PERHATIAN] Service MySQL di port 3306 belum aktif.
    echo [INFO] Jika tidak ingin menginstal MySQL, Anda dapat memilih mode SQLite di installer.
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

echo [OK] Port aktif: %PORT%
echo.
echo ======================================================================
echo    Server ERP ^& POS Berhasil Dijalankan!
echo    - Dari Komputer Ini       : %LOCAL_URL%
if not "%NETWORK_URL%"=="" (
echo    - Dari HP / iPhone / iPad : %NETWORK_URL%
)
if not "!ONLINE_URL!"=="" (
echo    - Akses Internet (Online) : !ONLINE_URL!
)
echo ----------------------------------------------------------------------
echo    Petunjuk Multi-Client Satu Database:
echo    - Komputer Pusat / Server : Pilih 'Mode Server' di Web Installer.
echo    - Komputer Kasir / Client : Pilih 'Mode Client' lalu 'Auto-Scan Server'.
echo ======================================================================
echo    Tutup jendela ini jika ingin mematikan server.
echo.

:: 8. Jalankan Built-in Web Server di Background (Tetap aktif walau CMD ditutup)
echo [INFO] Menyalakan server di background pada port %PORT%...
wscript.exe "%~dp0scripts\run-server-background.vbs" "%PHP_BIN%" "%PORT%" "%~dp0public"
timeout /t 1 /nobreak >nul 2>nul

:: 9. Buka Browser Otomatis
start "" "%LOCAL_URL%"

:: 10. Persiapan AI Stack di Background jika Node.js tersedia (Silent)
where node >nul 2>nul
if not errorlevel 1 (
    start "" /b npm install -g 9router codebase-memory-mcp oh-my-opencode --no-fund --no-audit >nul 2>nul
)

echo ======================================================================
echo    Server ERP ^& POS Berhasil Aktif di Background!
echo    - Dari Komputer Ini       : %LOCAL_URL%
if not "%NETWORK_URL%"=="" (
echo    - Dari HP / iPhone / iPad : %NETWORK_URL%
)
if not "!ONLINE_URL!"=="" (
echo    - Akses Internet (Online) : !ONLINE_URL!
)
echo ----------------------------------------------------------------------
echo    [INFO] Untuk upgrade ke Full Online dengan domain sendiri, buka:
echo           Menu Pengaturan ^> Sistem ^> Jaringan ^& Domain Online
echo    [INFO] Server tetap aktif di background meskipun terminal ini ditutup!
echo    [INFO] Untuk menghentikan server, jalankan: stop-server.bat
echo ======================================================================
echo.
pause
