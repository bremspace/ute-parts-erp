@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Bremspace ERP dan POS System - Stop Server

echo ======================================================================
echo    Bremspace ERP dan POS System - Penghenti Server (Port 1741-1760)
echo ======================================================================
echo.

set "STOPPED=0"

rem 0. Hentikan kontainer Docker jika Docker Compose aktif
if exist "%~dp0compose.yaml" (
    where docker >nul 2>nul
    if not errorlevel 1 (
        echo [INFO] Menghentikan kontainer Docker Bremspace...
        docker compose down >nul 2>nul
        set "STOPPED=1"
    )
)

rem 1. Hentikan proses via PID file jika ada
if exist "%~dp0storage\framework\server.pid" (
    set /p SERVER_PID=<"%~dp0storage\framework\server.pid"
    if defined SERVER_PID (
        taskkill /f /pid !SERVER_PID! >nul 2>nul
        if not errorlevel 1 (
            echo [OK] Web Server (PID: !SERVER_PID!) berhasil dihentikan.
            set "STOPPED=1"
        )
    )
    del /f /q "%~dp0storage\framework\server.pid" >nul 2>nul
)

if exist "%~dp0storage\framework\queue.pid" (
    set /p QUEUE_PID=<"%~dp0storage\framework\queue.pid"
    if defined QUEUE_PID (
        taskkill /f /pid !QUEUE_PID! >nul 2>nul
        if not errorlevel 1 (
            echo [OK] Queue Worker (PID: !QUEUE_PID!) berhasil dihentikan.
            set "STOPPED=1"
        )
    )
    del /f /q "%~dp0storage\framework\queue.pid" >nul 2>nul
)

rem 2. Hentikan proses yang mendengarkan di rentang port 1741-1760
for /l %%P in (1741,1,1760) do (
    for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr /r /c:":%%P " ^| findstr LISTENING') do (
        taskkill /f /pid %%a >nul 2>nul
        set "STOPPED=1"
        echo [OK] Menghentikan proses server pada port %%P (PID %%a).
    )
)

rem 3. Hentikan queue worker atau built-in server PHP aplikasi
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" 2>$null | Where-Object { $_.CommandLine -like '*queue:work*' -or $_.CommandLine -like '*-S 0.0.0.0:174*' -or $_.CommandLine -like '*-S 0.0.0.0:175*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue; Write-Host ('[OK] Menghentikan background PHP worker PID: ' + $_.ProcessId) }" 2>nul

if "!STOPPED!"=="1" (
    echo.
    echo ======================================================================
    echo [OK] Seluruh background service server Bremspace berhasil dihentikan.
    echo ======================================================================
) else (
    echo.
    echo [INFO] Tidak ada instance server Bremspace yang sedang aktif.
)

pause
