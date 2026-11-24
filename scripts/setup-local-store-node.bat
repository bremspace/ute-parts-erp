@echo off
REM ==============================================================================
REM Bremspace ERP — Script Setup PC Toko (Windows Helper)
REM Modul: Hybrid Database Sync (Offline-Ready & Cloud Failover)
REM ADR: 0013 | Target: PC Toko Windows (XAMPP / Laragon / Standalone PHP)
REM ==============================================================================

chcp 65001 >nul
echo ======================================================================
echo    Bremspace ERP — Setup PC Toko Windows (Mode Offline-First)
echo ======================================================================
echo.

REM 1. Pengecekan PHP
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP tidak ditemukan di PATH Windows.
    echo Pastikan PHP sudah terinstal dan ditambahkan ke System Environment Variables.
    pause
    exit /b 1
)

echo [OK] PHP terdeteksi.

REM 2. Menyiapkan file .env
if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
        echo [OK] Berhasil menyalin .env dari .env.example
    ) else (
        type nul > .env
        echo [OK] File .env dibuat baru.
    )
)

REM 3. Konfigurasi Parameter Hybrid Sync
echo.
echo Mengonfigurasi parameter Hybrid Sync di .env...
findstr /m "SYNC_ENABLED" .env >nul
if %errorlevel% neq 0 (
    echo.>> .env
    echo SYNC_ENABLED=true>> .env
    echo SYNC_NODE_ROLE=primary_store>> .env
    echo QUEUE_CONNECTION=database>> .env
) else (
    echo [OK] Parameter SYNC sudah terdaftar di .env.
)

REM 4. Generate App Key jika belum ada
findstr /m "base64:" .env >nul
if %errorlevel% neq 0 (
    echo Menghasilkan APP_KEY...
    php artisan key:generate --force
)

REM 5. Migrasi Database Lokal
echo.
echo Menjalankan migrasi database...
php artisan migrate --force

REM 6. Bersihkan Cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

REM 7. Buat Launcher Kasir Desktop Buka-Kasir.bat
echo.
echo Membuat shortcut launcher Buka-Kasir.bat...
(
echo @echo off
echo chcp 65001 ^>nul
echo echo Membuka Kasir Bremspace ERP...
echo start "" http://localhost:8000/app/pos
echo php artisan serve --port=8000
) > Buka-Kasir.bat

echo [OK] Buka-Kasir.bat siap digunakan.
echo.
echo ======================================================================
echo    SETUP PC TOKO SELESAI!
echo ======================================================================
echo Klik dua kali "Buka-Kasir.bat" untuk membuka kasir toko secara otomatis.
echo.
pause
