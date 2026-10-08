@echo off
setlocal enabledelayedexpansion

echo ====================================================
echo    RESTORE & SETUP ENVIRONMENT WINDOWS (NATIVE)
echo    Ute Parts ERP + OpenCode + OMO + Sesi Chat
echo ====================================================
echo.

:: 1. Cek File Migrasi
if not exist "opencode-migration-pack.tar.gz" (
    echo [ERROR] File opencode-migration-pack.tar.gz TIDAK DITEMUKAN!
    echo Salin file opencode-migration-pack.tar.gz dari VPS ke folder ini dulu.
    pause
    exit /b 1
)

:: 2. Cek Tar di Windows
where tar >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Perintah 'tar' tidak ditemukan di Windows.
    pause
    exit /b 1
)

:: 3. Ekstrak Paket Migrasi
echo [1/5] Mengekstrak paket arsip migrasi...
if not exist "temp_migration" mkdir "temp_migration"
tar -xzf opencode-migration-pack.tar.gz -C temp_migration

:: 4. Restore Sesi & Konfigurasi ke %USERPROFILE%
echo [2/5] Memulihkan Sesi Chat, OMO Config, Skills, dan MCP...
set "TARGET_CONFIG=%USERPROFILE%\.config"
set "TARGET_SHARE=%USERPROFILE%\.local\share"

if not exist "%TARGET_CONFIG%" mkdir "%TARGET_CONFIG%"
if not exist "%TARGET_SHARE%" mkdir "%TARGET_SHARE%"

tar -xzf temp_migration\opencode-data.tar.gz -C "%USERPROFILE%"

if exist "temp_migration\project.env" (
    if not exist ".env" (
        copy temp_migration\project.env .env >nul
        echo [OK] File .env berhasil dipulihkan ke project.
    )
)

rd /s /q temp_migration
echo [OK] Data sesi chat, OMO config, skills berhasil dipulihkan.

:: 5. Cek Node.js & NPM
echo.
echo [3/5] Memeriksa Node.js...
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [INFO] Node.js belum terpasang. Menginstall via winget...
    winget install OpenJS.NodeJS.LTS --accept-package-agreements --accept-source-agreements
) else (
    echo [OK] Node.js terdeteksi.
)

:: 6. Cek OpenCode
echo.
echo [4/5] Memeriksa OpenCode...
where opencode >nul 2>nul
if %errorlevel% neq 0 (
    echo [INFO] Memasang OpenCode via npm...
    call npm install -g opencode-ai
) else (
    echo [OK] OpenCode terdeteksi.
)

:: 7. Install Dependensi Project Laravel
echo.
echo [5/5] Memasang dependensi project...
where composer >nul 2>nul
if %errorlevel% equ 0 (
    call composer install --no-interaction
) else (
    echo [PERINGATAN] Composer belum terpasang di Windows. Silakan pasang Composer: https://getcomposer.org/download/
)

call npm install
call npm run build

echo.
echo ====================================================
echo    RESTORE SELESAI DENGAN SUKSES!
echo ====================================================
echo Untuk melanjutkan sesi percakapan, jalankan:
echo    opencode
echo.
pause
