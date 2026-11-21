@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PROJECT_DIR=%~dp0.."
set "GIT_URL=https://github.com/bremspace/ute-parts-erp"
set "GIT_BRANCH=main"

echo ============================================================
echo   UTE PARTS ERP - Docker Installer (Windows)
echo ============================================================
echo.
echo Pilih sumber instalasi:
echo   [1] Gunakan folder lokal (default) - offline, pakai folder yang sudah disalin
echo   [2] Git pull/clone dari URL - online, ambil/update source dari GitHub
echo.
set /p "CHOICE=Pilihan (1/2, default 1): "
if "%CHOICE%"=="" set "CHOICE=1"

if "%CHOICE%"=="2" (
    where git >nul 2>nul
    if errorlevel 1 (
        echo [ERROR] Git tidak ditemukan. Install Git for Windows terlebih dahulu.
        pause
        exit /b 1
    )
    if exist "%PROJECT_DIR%\.git" (
        echo [INFO] Folder lokal terdeteksi sebagai repo Git. Menarik update terbaru...
        git -C "%PROJECT_DIR%" fetch origin
        if errorlevel 1 ( echo [ERROR] git fetch gagal. & pause & exit /b 1 )
        git -C "%PROJECT_DIR%" pull origin %GIT_BRANCH%
        if errorlevel 1 ( echo [ERROR] git pull gagal. & pause & exit /b 1 )
    ) else (
        echo [INFO] Folder lokal bukan repo Git. Melakukan clone dari %GIT_URL% ...
        git clone --branch %GIT_BRANCH% %GIT_URL% "%PROJECT_DIR%"
        if errorlevel 1 ( echo [ERROR] git clone gagal. & pause & exit /b 1 )
    )
    echo [INFO] Source siap.
)

where docker >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Docker tidak ditemukan. Install Docker Desktop dulu.
    pause
    exit /b 1
)

docker info >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Docker daemon tidak berjalan. Buka Docker Desktop.
    pause
    exit /b 1
)

echo [INFO] Building dan menjalankan container...
docker compose up -d --build --force-recreate app
if errorlevel 1 (
    echo [ERROR] Gagal menjalankan docker compose.
    pause
    exit /b 1
)

echo.
echo [SUKSES] Container UTE_PARTS_ERP berjalan di http://localhost
echo Buka browser: http://localhost
pause
exit /b 0