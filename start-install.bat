@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>nul
title ERP & POS System - Universal One-Click Launcher (Port 1741)
cd /d "%~dp0"

echo ======================================================================
echo    ERP & POS System - Universal Local & Network Launcher
echo    Port Default: 1741 (Akses Lokal & Jaringan Wi-Fi/LAN/HP/iOS/Kasir)
echo ======================================================================
echo.

rem -----------------------------------------------------------------------------
rem 1. Deteksi PHP Binary (PHP minimal 8.4)
rem -----------------------------------------------------------------------------
set "PHP_BIN="

rem A. Cek tools\php lokal bawaan project jika sudah siap
if exist "%~dp0tools\php\php.exe" (
    call :TEST_PHP "%~dp0tools\php\php.exe"
    if defined PHP_BIN goto :PHP_CHECK_DONE
)

rem B. Cek System PATH
where php.exe >nul 2>nul
if not errorlevel 1 (
    call :TEST_PHP "php"
    if defined PHP_BIN goto :PHP_CHECK_DONE
)

rem C. Cek Laragon PHP 8.6, 8.5, 8.4 di drive C:, D:, E:
for %%D in (C D E) do (
    for %%V in (php-8.6 php-8.5 php-8.4) do (
        for /d %%P in ("%%D:\laragon\bin\php\%%V*") do (
            if exist "%%P\php.exe" (
                call :TEST_PHP "%%P\php.exe"
                if defined PHP_BIN goto :PHP_CHECK_DONE
            )
        )
    )
)

rem D. Cek XAMPP PHP di drive C:, D:, E:
for %%D in (C D E) do (
    if exist "%%D:\xampp\php\php.exe" (
        call :TEST_PHP "%%D:\xampp\php\php.exe"
        if defined PHP_BIN goto :PHP_CHECK_DONE
    )
)

rem E. Cek direktori umum lain (C:\php, C:\php84, Program Files)
for %%P in ("C:\php\php.exe" "C:\php84\php.exe" "C:\Program Files\PHP\php.exe") do (
    if exist %%P (
        call :TEST_PHP %%P
        if defined PHP_BIN goto :PHP_CHECK_DONE
    )
)

rem F. Auto-Setup PHP 8.4 Portable jika belum ada di PC Windows bersih
echo [INFO] PHP minimal 8.4 belum terdeteksi di komputer.
echo [INFO] Menyiapkan PHP 8.4 Portable & Runtime Windows secara otomatis...

if not exist "%~dp0tools" mkdir "%~dp0tools" 2>nul

if exist "%~dp0scripts\setup-windows-php.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-windows-php.ps1"
)

if exist "%~dp0tools\php\php.exe" (
    call :TEST_PHP "%~dp0tools\php\php.exe"
    if defined PHP_BIN goto :PHP_CHECK_DONE
)

rem G. Coba pasang via Windows Package Manager (winget) jika tersedia
where winget >nul 2>nul
if not errorlevel 1 (
    echo.
    echo [INFO] Mencoba memasang PHP 8.4 melalui Windows Package Manager (winget)...
    winget install --id PHP.PHP.8.4 -e --silent --accept-source-agreements --accept-package-agreements
    where php.exe >nul 2>nul
    if not errorlevel 1 (
        call :TEST_PHP "php"
        if defined PHP_BIN goto :PHP_CHECK_DONE
    )
)

:PHP_CHECK_DONE
if not defined PHP_BIN (
    echo.
    echo ======================================================================
    echo [ERROR] PHP minimal 8.4 tidak berhasil disiapkan secara otomatis.
    echo ======================================================================
    echo Pilihan Solusi Manual (Sangat Mudah):
    echo 1. Unduh & pasang Laragon Full (mencakup PHP 8.4+ & MySQL otomatis):
    echo    https://laragon.org/download/
    echo 2. ATAU jalankan perintah ini di Windows Terminal / CMD:
    echo    winget install PHP.PHP.8.4
    echo 3. ATAU unduh zip PHP 8.4 Thread Safe x64 dari:
    echo    https://windows.php.net/download/
    echo    lalu ekstrak seluruh isinya ke dalam folder: %~dp0tools\php\
    echo ======================================================================
    echo.
    pause
    exit /b 1
)

rem Masukkan folder PHP ke depan PATH agar pemanggilan command 'php' konsisten
if "%PHP_BIN%" neq "php" (
    for %%F in ("%PHP_BIN%") do (
        if exist "%%~dpF" (
            set "PATH=%%~dpF;!PATH!"
        )
    )
)

echo [OK] PHP terverifikasi: %PHP_BIN%

rem -----------------------------------------------------------------------------
rem 2. Auto-Fix Direktori Storage, Database & Cache
rem -----------------------------------------------------------------------------
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" 2>nul
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" 2>nul
if not exist "storage\framework\views" mkdir "storage\framework\views" 2>nul
if not exist "storage\framework\testing" mkdir "storage\framework\testing" 2>nul
if not exist "storage\logs" mkdir "storage\logs" 2>nul
if not exist "storage\app\public" mkdir "storage\app\public" 2>nul
if not exist "bootstrap\cache" mkdir "bootstrap\cache" 2>nul
if not exist "database" mkdir "database" 2>nul
attrib -r "storage\*.*" /s /d >nul 2>nul

rem -----------------------------------------------------------------------------
rem 3. Deteksi & Siapkan Composer
rem -----------------------------------------------------------------------------
set "USE_COMPOSER_PHAR=0"
if exist "%~dp0composer.phar" (
    set "USE_COMPOSER_PHAR=1"
    echo [OK] Composer portable (composer.phar) siap digunakan.
) else (
    where composer >nul 2>nul
    if not errorlevel 1 (
        echo [OK] Composer global terdeteksi di system PATH.
    ) else (
        echo [INFO] Composer belum ada di system PATH. Mengunduh composer.phar...
        powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13; (New-Object System.Net.WebClient).DownloadFile('https://getcomposer.org/composer-stable.phar', 'composer.phar')" 2>nul
        if not exist "%~dp0composer.phar" (
            powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -Uri 'https://getcomposer.org/composer-stable.phar' -OutFile 'composer.phar' -UseBasicParsing } catch {}" 2>nul
        )
        if exist "%~dp0composer.phar" (
            set "USE_COMPOSER_PHAR=1"
            echo [OK] Composer portable (composer.phar) berhasil diunduh ^& siap digunakan.
        )
    )
)

rem -----------------------------------------------------------------------------
rem 4. Auto-Fix Dependensi Vendor
rem -----------------------------------------------------------------------------
if not exist "vendor\autoload.php" (
    echo [INFO] Menjalankan composer install untuk melengkapi dependensi vendor...
    echo [INFO] Normal memakan waktu 1-3 menit untuk instalasi pertama kali. Mohon tunggu...
    
    if "!USE_COMPOSER_PHAR!"=="1" (
        "%PHP_BIN%" "%~dp0composer.phar" install --no-interaction --prefer-dist --no-scripts
        if not exist "vendor\autoload.php" (
            echo [WARN] Fallback composer install dengan toleransi platform requirements...
            "%PHP_BIN%" "%~dp0composer.phar" install --no-interaction --prefer-dist --no-scripts --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter
        )
        if not exist "vendor\autoload.php" (
            "%PHP_BIN%" "%~dp0composer.phar" install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs
        )
    ) else (
        where composer >nul 2>nul
        if not errorlevel 1 (
            call composer install --no-interaction --prefer-dist --no-scripts
            if not exist "vendor\autoload.php" (
                echo [WARN] Fallback composer install dengan toleransi platform requirements...
                call composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter
            )
            if not exist "vendor\autoload.php" (
                call composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs
            )
        ) else (
            echo [WARN] Composer belum tersedia. Dependensi dapat dilengkapi via Web GUI Installer.
        )
    )

    if exist "vendor\autoload.php" (
        echo [OK] Dependensi vendor berhasil dipasang.
        echo [INFO] Mendaftarkan package Laravel...
        "%PHP_BIN%" "%~dp0artisan" package:discover --ansi >nul 2>nul
    ) else (
        echo [INFO] Dependensi vendor dapat dilengkapi nanti melalui Web Installer.
    )
) else (
    echo [OK] Dependensi vendor aplikasi sudah terpasang.
)

rem -----------------------------------------------------------------------------
rem 5. Deteksi Status Instalasi (PC Baru vs PC Yang Sudah Terinstal Sebelumnya)
rem -----------------------------------------------------------------------------
set "IS_INSTALLED=0"
if exist "%~dp0storage\installed.lock" (
    set "IS_INSTALLED=1"
) else if exist "%~dp0.env" (
    findstr /m /c:"APP_KEY=" "%~dp0.env" >nul 2>nul
    if not errorlevel 1 (
        findstr /m /c:"DB_CONNECTION=" "%~dp0.env" >nul 2>nul
        if not errorlevel 1 (
            set "IS_INSTALLED=1"
        )
    )
)

rem Perlakuan khusus jika aplikasi telah terinstal sebelumnya
if "!IS_INSTALLED!"=="1" (
    echo [OK] Status: Aplikasi telah terkonfigurasi sebelumnya di PC ini.
    echo [INFO] Membersihkan cache aplikasi usang agar sinkron dengan file terbaru...
    "%PHP_BIN%" "%~dp0artisan" optimize:clear >nul 2>nul
    
    if not exist "%~dp0public\storage" (
        "%PHP_BIN%" "%~dp0artisan" storage:link >nul 2>nul
    )

    if exist "%~dp0.env" (
        findstr /m /c:"DB_CONNECTION=sqlite" "%~dp0.env" >nul 2>nul
        if not errorlevel 1 (
            if not exist "%~dp0database\database.sqlite" (
                type nul > "%~dp0database\database.sqlite" 2>nul
            )
        )
    )
) else (
    echo [INFO] Status: Instalasi baru / Belum terkonfigurasi. Membuka Web Setup Wizard...
)

rem -----------------------------------------------------------------------------
rem 6. Konfigurasi Port 1741 & Penanganan Proses Lama (Auto-Restart Bersih)
rem -----------------------------------------------------------------------------
set "PORT=1741"
netstat -ano | findstr /r /c:":!PORT! " | findstr LISTENING >nul 2>nul
if not errorlevel 1 (
    set "PHP_ZOMBIE_FOUND=0"
    for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr /r /c:":1741 " ^| findstr LISTENING') do (
        tasklist /fi "PID eq %%a" 2>nul | findstr /i "php.exe" >nul 2>nul
        if not errorlevel 1 (
            echo [INFO] Server lama di port !PORT! (PID %%a) terdeteksi. Merestart proses server...
            taskkill /f /pid %%a >nul 2>nul
            set "PHP_ZOMBIE_FOUND=1"
        )
    )
    if "!PHP_ZOMBIE_FOUND!"=="1" (
        timeout /t 1 /nobreak >nul 2>nul
    )

    netstat -ano | findstr /r /c:":!PORT! " | findstr LISTENING >nul 2>nul
    if not errorlevel 1 (
        echo [WARN] Port !PORT! digunakan oleh aplikasi lain, mencari port alternatif...
        for /l %%P in (1742,1,1760) do (
            netstat -ano | findstr /r /c:":%%P " | findstr LISTENING >nul 2>nul
            if errorlevel 1 (
                set "PORT=%%P"
                goto :PORT_FOUND
            )
        )
    )
)
:PORT_FOUND

rem -----------------------------------------------------------------------------
rem 7. Deteksi IP Jaringan Lokal (IPv4 LAN/Wi-Fi untuk Akses Multi-Device)
rem -----------------------------------------------------------------------------
set "LAN_IP="
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    if not defined LAN_IP (
        set "RAW_IP=%%a"
        set "CLEAN_IP=!RAW_IP: =!"
        echo !CLEAN_IP! | findstr /v "127.0.0.1 169.254." >nul 2>nul
        if not errorlevel 1 (
            set "LAN_IP=!CLEAN_IP!"
        )
    )
)

rem -----------------------------------------------------------------------------
rem 8. Cek & Otomatis Nyalakan Service MySQL / MariaDB lokal jika port 3306 belum aktif
rem -----------------------------------------------------------------------------
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if errorlevel 1 (
    net start MySQL >nul 2>nul
    net start MySQL80 >nul 2>nul
    net start MariaDB >nul 2>nul
    net start wampmysqld64 >nul 2>nul

    for %%D in (C D E) do (
        if exist "%%D:\laragon\bin\mysql" (
            for /d %%M in ("%%D:\laragon\bin\mysql\*") do (
                if exist "%%M\bin\mysqld.exe" (
                    echo [INFO] Menyalakan service MySQL lokal Laragon di background...
                    start "" /b "%%M\bin\mysqld.exe" --defaults-file="%%M\my.ini" --standalone >nul 2>nul
                )
            )
        )
        if exist "%%D:\xampp\mysql\bin\mysqld.exe" (
            echo [INFO] Menyalakan service MySQL lokal XAMPP di background...
            start "" /b "%%D:\xampp\mysql\bin\mysqld.exe" --standalone >nul 2>nul
        )
    )
)

rem Buka port web server di Windows Defender Firewall (Port !PORT!)
netsh advfirewall firewall add rule name="ERP POS System (Port !PORT!)" dir=in action=allow protocol=TCP localport=!PORT! >nul 2>nul

rem Cek kembali ketersediaan port 3306 MySQL
set "MYSQL_ACTIVE=0"
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if not errorlevel 1 (
    set "MYSQL_ACTIVE=1"
)

rem -----------------------------------------------------------------------------
rem 9. Tentukan Target URL Sesuai Kondisi Sistem
rem -----------------------------------------------------------------------------
set "TARGET_PATH=/install.php"
if "!IS_INSTALLED!"=="1" (
    if "!MYSQL_ACTIVE!"=="1" (
        set "TARGET_PATH=/app/login"
    ) else if exist "%~dp0.env" (
        findstr /m /c:"DB_CONNECTION=sqlite" "%~dp0.env" >nul 2>nul
        if not errorlevel 1 (
            set "TARGET_PATH=/app/login"
        )
    )
)

if "!MYSQL_ACTIVE!"=="0" (
    if "!IS_INSTALLED!"=="0" (
        echo [INFO] Service MySQL port 3306 tidak terdeteksi.
        echo [INFO] Anda dapat memilih opsi database 'SQLite' di installer tanpa perlu install MySQL.
    )
)

set "LOCAL_URL=http://localhost:!PORT!!TARGET_PATH!"
set "NETWORK_URL="
if not "!LAN_IP!"=="" (
    set "NETWORK_URL=http://!LAN_IP!:!PORT!!TARGET_PATH!"
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

rem -----------------------------------------------------------------------------
rem 10. Jalankan Built-in Web Server di Background & Verifikasi Port
rem -----------------------------------------------------------------------------
echo [INFO] Menyalakan server di background pada port !PORT!...

rem Prioritas 1: Jalankan via PowerShell Start-Process (asinkron, native, terisolasi)
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%PHP_BIN%' -ArgumentList '-S', '0.0.0.0:!PORT!', '-t', '%~dp0public' -WindowStyle Hidden -RedirectStandardOutput '%~dp0storage\logs\server.log' -RedirectStandardError '%~dp0storage\logs\server.log'" 2>nul

timeout /t 1 /nobreak >nul 2>nul

rem Cek apakah server sudah aktif mendengarkan
netstat -ano | findstr /r /c:":!PORT! " | findstr LISTENING >nul 2>nul
if errorlevel 1 (
    rem Fallback 2: Jalankan via WScript VBS
    if exist "%~dp0scripts\run-server-background.vbs" (
        wscript.exe "%~dp0scripts\run-server-background.vbs" "%PHP_BIN%" "!PORT!" "%~dp0public" "%~dp0storage\logs\server.log"
        timeout /t 1 /nobreak >nul 2>nul
    )
)

netstat -ano | findstr /r /c:":!PORT! " | findstr LISTENING >nul 2>nul
if errorlevel 1 (
    rem Fallback 3: Jalankan via start /b
    start "" /b "%PHP_BIN%" -S 0.0.0.0:!PORT! -t "%~dp0public" >> "%~dp0storage\logs\server.log" 2>&1
    timeout /t 1 /nobreak >nul 2>nul
)

rem -----------------------------------------------------------------------------
rem 11. Buka Browser Otomatis
rem -----------------------------------------------------------------------------
start "" "!LOCAL_URL!"

rem -----------------------------------------------------------------------------
rem 12. Persiapan AI Stack di Background (Opsional, Hanya jika Node.js tersedia)
rem -----------------------------------------------------------------------------
where node >nul 2>nul
if not errorlevel 1 (
    where 9router >nul 2>nul
    if errorlevel 1 (
        start "" /b npm install -g 9router codebase-memory-mcp oh-my-opencode --no-fund --no-audit >nul 2>nul
    )
)

echo.
echo ======================================================================
echo    Server ERP ^& POS Berhasil Aktif di Background
echo    Port Aktif: !PORT!
echo ----------------------------------------------------------------------
echo    - Dari Komputer Ini       : !LOCAL_URL!
if not "!NETWORK_URL!"=="" (
echo    - Dari HP / Kasir / Wi-Fi : !NETWORK_URL!
)
if not "!ONLINE_URL!"=="" (
echo    - Akses Internet (Online) : !ONLINE_URL!
)
if "!IS_INSTALLED!"=="1" (
echo ----------------------------------------------------------------------
echo    [TIPS] Ingin reset / ubah konfigurasi database?
echo           Buka Web Installer di: http://localhost:!PORT!/install.php
)
echo ----------------------------------------------------------------------
echo    [INFO] Server tetap aktif di background meskipun jendela ini ditutup.
echo    [INFO] Untuk mematikan server kapan saja, jalankan: stop-server.bat
echo ======================================================================
echo.
pause
exit /b 0

rem =============================================================================
rem SUBROUTINE: Uji Versi PHP (Minimal PHP 8.4)
rem =============================================================================
:TEST_PHP
set "CANDIDATE=%~1"
for /f "tokens=1,2 delims=." %%a in ('"%CANDIDATE%" -r "echo PHP_VERSION;" 2^>nul') do (
    set "PHP_MAJ=%%a"
    set "PHP_MIN=%%b"
    if "!PHP_MAJ!" gtr "8" (
        set "PHP_BIN=%CANDIDATE%"
        exit /b 0
    )
    if "!PHP_MAJ!" equ "8" (
        if !PHP_MIN! geq 4 (
            set "PHP_BIN=%CANDIDATE%"
            exit /b 0
        )
    )
)
exit /b 1
