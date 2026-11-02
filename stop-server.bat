@echo off
chcp 65001 >nul
title ERP & POS System - Stop Server

echo ======================================================================
echo    ERP & POS System - Penghenti Background Server (Port 1741)
echo ======================================================================
echo.

set "STOPPED=0"
for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr :1741 ^| findstr LISTENING') do (
    taskkill /f /pid %%a >nul 2>nul
    set "STOPPED=1"
)

:: Hentikan queue worker jika ada
wmic process where "name='php.exe' and CommandLine like '%%queue:work%%'" call terminate >nul 2>nul

if "%STOPPED%"=="1" (
    echo [OK] Web Server & Layanan ERP & POS berhasil dihentikan.
) else (
    echo [INFO] Tidak ada server ERP & POS yang sedang berjalan di port 1741.
)

echo.
pause
