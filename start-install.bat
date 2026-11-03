@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Bremspace ERP dan POS System - Universal Launcher (Port 1741)
cd /d "%~dp0"

echo ======================================================================
echo    Bremspace ERP dan POS System - One-Click Universal Launcher
echo    Target Port: 1741 (Localhost dan Jaringan Wi-Fi/LAN)
echo ======================================================================
echo.

:: 1. Deteksi PowerShell di sistem
set "POWERSHELL_BIN=powershell.exe"
where powershell.exe >nul 2>nul
if errorlevel 1 (
    if exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
        set "POWERSHELL_BIN=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
    ) else (
        echo [ERROR] Windows PowerShell tidak ditemukan di sistem ini.
        echo.
        echo ======================================================================
        echo Tekan tombol apa saja untuk menutup jendela ini...
        pause >nul
        exit /b 1
    )
)

:: 2. Prioritaskan Docker Engine (WSL2 Native / Desktop) dengan Fallback Lokal
if exist "%~dp0scripts\install-docker.ps1" (
    "%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install-docker.ps1" %*
) else (
    "%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install.ps1" %*
)

set "EXIT_CODE=%ERRORLEVEL%"

echo.
echo ======================================================================
if %EXIT_CODE% neq 0 (
    echo [INFO] Inisialisasi selesai dengan status kode: %EXIT_CODE%
    echo Silakan periksa pesan di atas jika terdapat kendala instalasi.
) else (
    echo [SUKSES] Seluruh tahapan peluncur sistem Bremspace berhasil diproses!
    echo Jendela ini dapat dibiarkan terbuka atau diminimize selama server berjalan.
)
echo ======================================================================
echo Tekan tombol apa saja untuk menutup jendela ini...
pause >nul

exit /b %EXIT_CODE%
