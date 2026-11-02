@echo off
setlocal
chcp 65001 >nul 2>nul
title "ERP dan POS System - Universal One-Click Launcher (Port 1741)"
cd /d "%~dp0"

echo ======================================================================
echo    ERP dan POS System - Universal Local dan Network Launcher
echo    Port Default: 1741 (Akses Lokal dan Jaringan Wi-Fi/LAN/HP/iOS/Kasir)
echo ======================================================================
echo.

rem Jalankan installer PowerShell dengan bypass execution policy
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install.ps1"

set "EXIT_CODE=%ERRORLEVEL%"
if %EXIT_CODE% neq 0 (
    echo.
    echo ======================================================================
    echo [ERROR] Eksekusi installer selesai dengan kode error: %EXIT_CODE%
    echo Periksa pesan di atas untuk detail kendala.
    echo ======================================================================
)

echo.
echo ======================================================================
echo Tekan tombol apa saja untuk menutup jendela ini...
echo ======================================================================
pause >nul
exit /b %EXIT_CODE%
