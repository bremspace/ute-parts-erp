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
echo Pilih mode instalasi:
echo   [1] Fresh install - hapus data lama, install ulang dari awal
echo   [2] Update dari Git - pull terbaru + rebuild, data tetap
echo   [3] Gunakan folder lokal - offline, tanpa git
echo.
set /p "MODE=Pilihan (1/2/3, default 1): "
if "%MODE%"=="" set "MODE=1"

if "%MODE%"=="1" (
    echo [INFO] Fresh install: menghapus container dan volume lama...
    docker compose down -v
    if errorlevel 1 ( echo [ERROR] Gagal down. & pause & exit /b 1 )
)

if "%MODE%"=="2" (
    where git >nul 2>nul
    if errorlevel 1 ( echo [ERROR] Git tidak ditemukan. & pause & exit /b 1 )
    if exist "%PROJECT_DIR%\.git" (
        echo [INFO] Menarik update terbaru dari Git...
        git -C "%PROJECT_DIR%" fetch origin
        if errorlevel 1 ( echo [ERROR] git fetch gagal. & pause & exit /b 1 )
        git -C "%PROJECT_DIR%" pull origin %GIT_BRANCH%
        if errorlevel 1 ( echo [ERROR] git pull gagal. & pause & exit /b 1 )
    ) else (
        echo [INFO] Clone dari %GIT_URL% ...
        git clone --branch %GIT_BRANCH% %GIT_URL% "%PROJECT_DIR%"
        if errorlevel 1 ( echo [ERROR] git clone gagal. & pause & exit /b 1 )
    )
)

if "%MODE%"=="3" (
    echo [INFO] Menggunakan folder lokal (offline). Data tetap.
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