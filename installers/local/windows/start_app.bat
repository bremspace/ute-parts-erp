@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>nul
title ERP & POS System - Start Application (Port 1741)

echo ======================================================================
echo    ERP & POS System - Application Launcher
echo    Memulai server & mempersiapkan seluruh layanan sistem...
echo ======================================================================
echo.

for %%I in ("%~dp0..\..\..") do set "ROOT_DIR=%%~fI"
cd /d "%ROOT_DIR%"

:: -----------------------------------------------------------------------------
:: 1. Deteksi PHP Binary (Wajib PHP >= 8.4)
:: -----------------------------------------------------------------------------
set "PHP_BIN="

if exist "%ROOT_DIR%\tools\php\php.exe" (
    "%ROOT_DIR%\tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%ROOT_DIR%\tools\php\php.exe"
        goto :PHP_CHECK_DONE
    )
)

if exist "C:\laragon\bin\php\php-8.5*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.5*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_CHECK_DONE
        )
    )
)

if exist "C:\laragon\bin\php\php-8.4*" (
    for /d %%D in ("C:\laragon\bin\php\php-8.4*") do (
        if exist "%%~D\php.exe" (
            set "PHP_BIN=%%~D\php.exe"
            goto :PHP_CHECK_DONE
        )
    )
)

where php >nul 2>nul
if not errorlevel 1 (
    php -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=php"
        goto :PHP_CHECK_DONE
    )
)

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=C:\xampp\php\php.exe"
        goto :PHP_CHECK_DONE
    )
)

:: Auto-Download PHP Portable jika belum ada sama sekali
echo [INFO] Menyiapkan PHP portable di folder tools...
if not exist "%ROOT_DIR%\tools" mkdir "%ROOT_DIR%\tools" 2>nul
if exist "%~dp0setup-windows-php.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-windows-php.ps1"
)
if exist "%ROOT_DIR%\tools\php\php.exe" (
    "%ROOT_DIR%\tools\php\php.exe" -r "exit(version_compare(PHP_VERSION, '8.4.0', '>=') ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 (
        set "PHP_BIN=%ROOT_DIR%\tools\php\php.exe"
    )
)

:PHP_CHECK_DONE
if "%PHP_BIN%"=="" (
    echo [ERROR] PHP minimal 8.4 belum terpasang atau gagal dieksekusi.
    echo Jalankan start-install.bat untuk memeriksa instalasi sistem.
    pause
    exit /b 1
)

echo [OK] PHP: %PHP_BIN%

:: -----------------------------------------------------------------------------
:: 2. Pengecekan Status Instalasi Sistem (Auto-Install jika Fresh PC)
:: -----------------------------------------------------------------------------
if not exist "%ROOT_DIR%\storage\installed.lock" (
    if not exist "%ROOT_DIR%\.env" (
        echo [INFO] Sistem belum terkonfigurasi di komputer ini.
        echo [INFO] Menjalankan installer otomatis (install.bat)...
        echo.
        call "%~dp0install.bat"
        exit /b %ERRORLEVEL%
    )
)

:: -----------------------------------------------------------------------------
:: 3. Persiapan Direktori Storage, Database & Cache
:: -----------------------------------------------------------------------------
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" 2>nul
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" 2>nul
if not exist "storage\framework\views" mkdir "storage\framework\views" 2>nul
if not exist "storage\logs" mkdir "storage\logs" 2>nul
if not exist "storage\app\public" mkdir "storage\app\public" 2>nul
if not exist "bootstrap\cache" mkdir "bootstrap\cache" 2>nul
if not exist "database" mkdir "database" 2>nul

:: -----------------------------------------------------------------------------
:: 3. Pastikan Symlink Storage Aktif
:: -----------------------------------------------------------------------------
if not exist "public\storage" (
    "%PHP_BIN%" artisan storage:link >nul 2>nul
)

:: -----------------------------------------------------------------------------
:: 4. Cek & Aktifkan MySQL Lokal jika port 3306 belum aktif
:: -----------------------------------------------------------------------------
netstat -ano | findstr /r /c:":3306 " >nul 2>nul
if errorlevel 1 (
    net start MySQL >nul 2>nul
    net start MySQL80 >nul 2>nul
    net start MariaDB >nul 2>nul
    if exist "%ROOT_DIR%\tools\mariadb\bin\mysqld.exe" (
        start "" /b "%ROOT_DIR%\tools\mariadb\bin\mysqld.exe" --defaults-file="%ROOT_DIR%\tools\mariadb\my.ini" --standalone >nul 2>nul
    )
    if exist "%ROOT_DIR%\tools\mysql\bin\mysqld.exe" (
        start "" /b "%ROOT_DIR%\tools\mysql\bin\mysqld.exe" --defaults-file="%ROOT_DIR%\tools\mysql\my.ini" --standalone >nul 2>nul
    )
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

:: -----------------------------------------------------------------------------
:: 5. Konfigurasi Port Server (Default: 1741)
:: -----------------------------------------------------------------------------
set "PORT=1741"
set "SERVER_ALREADY_RUNNING=0"
netstat -ano | findstr /r /c:":%PORT% " >nul 2>nul
if not errorlevel 1 (
    set "SERVER_ALREADY_RUNNING=1"
)

:: -----------------------------------------------------------------------------
:: 6. Nyalakan Web Server di Background jika belum aktif
:: -----------------------------------------------------------------------------
if "%SERVER_ALREADY_RUNNING%"=="0" (
    echo [INFO] Menyalakan Web Server di port %PORT%...
    wscript.exe "%~dp0run-server-background.vbs" "%PHP_BIN%" "%PORT%" "%ROOT_DIR%\public"
    timeout /t 1 /nobreak >nul 2>nul
    echo [OK] Web Server aktif di background (Port %PORT%).
) else (
    echo [OK] Web Server sudah aktif di port %PORT%.
)

:: -----------------------------------------------------------------------------
:: 7. Nyalakan Background Queue Worker, Scheduler & Jalankan Sinkronisasi Awal
:: -----------------------------------------------------------------------------
powershell -NoProfile -ExecutionPolicy Bypass -Command "if (-not (Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*queue:work*' })) { exit 1 }" >nul 2>nul
if errorlevel 1 (
    if exist "%ROOT_DIR%\.env" (
        echo [INFO] Menyalakan Background Queue Worker...
        wscript.exe "%~dp0run-command-background.vbs" "\"%PHP_BIN%\" \"%ROOT_DIR%\artisan\" queue:work --queue=sync,default --sleep=3 --tries=3"
        echo [OK] Queue Worker aktif di background.
    )
) else (
    echo [OK] Queue Worker sudah aktif.
)

:: Jalankan background scheduler (cron runner) di Windows
powershell -NoProfile -ExecutionPolicy Bypass -Command "if (-not (Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*schedule:work*' })) { exit 1 }" >nul 2>nul
if errorlevel 1 (
    if exist "%ROOT_DIR%\.env" (
        echo [INFO] Menyalakan Background Scheduler (Schedule Work)...
        wscript.exe "%~dp0run-command-background.vbs" "\"%PHP_BIN%\" \"%ROOT_DIR%\artisan\" schedule:work"
        echo [OK] Background Scheduler aktif.
    )
) else (
    echo [OK] Background Scheduler sudah aktif.
)

:: Sinkronisasi Awal saat Toko Buka (Tarik pesanan online tadi malam & kirim outbox tertunda)
if exist "%ROOT_DIR%\.env" (
    echo [INFO] Memeriksa data baru dari Cloud VPS (Catch-up sync)...
    "%PHP_BIN%" "%ROOT_DIR%\artisan" sync:pull
    "%PHP_BIN%" "%ROOT_DIR%\artisan" sync:flush
    echo [OK] Sinkronisasi awal selesai.
)

:: -----------------------------------------------------------------------------
:: 8. Deteksi IP Jaringan Lokal (IPv4 LAN/Wi-Fi)
:: -----------------------------------------------------------------------------
set "LAN_IP="
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    if not defined LAN_IP (
        set "RAW_IP=%%a"
        set "LAN_IP=!RAW_IP: =!"
    )
)

:: -----------------------------------------------------------------------------
:: 10. Tentukan Rute Tujuan (Default: /app/login)
:: -----------------------------------------------------------------------------
set "TARGET_PATH=/app/login"

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

:: -----------------------------------------------------------------------------
:: 10. Buat Shortcut di Desktop jika belum ada
:: -----------------------------------------------------------------------------
if not exist "%USERPROFILE%\Desktop\Bremspace ERP POS.lnk" (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws = New-Object -ComObject WScript.Shell; $desktop = [System.Environment]::GetFolderPath('Desktop'); $s = $ws.CreateShortcut([System.IO.Path]::Combine($desktop, 'Bremspace ERP POS.lnk')); $s.TargetPath = '%~dp0start_app.bat'; $s.WorkingDirectory = '%~dp0'; if (Test-Path '%~dp0public\favicon.ico') { $s.IconLocation = '%~dp0public\favicon.ico' }; $s.Description = 'Bremspace ERP & POS System'; $s.Save()" >nul 2>nul
)

:: -----------------------------------------------------------------------------
:: 11. Buka Browser Otomatis
:: -----------------------------------------------------------------------------
start "" "%LOCAL_URL%"

echo.
echo ======================================================================
echo    Aplikasi ERP ^& POS Berhasil Berjalan
echo ======================================================================
echo    - Dari Komputer Ini        : %LOCAL_URL%
if not "%NETWORK_URL%"=="" (
echo    - Dari HP / Android / iOS  : %NETWORK_URL%
)
if not "!ONLINE_URL!"=="" (
echo    - Akses Internet (Online)  : !ONLINE_URL!
)
echo ----------------------------------------------------------------------
echo    [TIPS] Tutup jendela ini jika sudah selesai; server tetap aktif.
echo    [INFO] Untuk menghentikan server secara penuh, jalankan: stop-server.bat
echo ======================================================================
echo.
timeout /t 3 /nobreak >nul 2>nul
exit /b 0
