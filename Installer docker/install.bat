@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo ============================================================
echo   UTE PARTS ERP - Docker Installer (Windows)
echo ============================================================
echo.

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