@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>nul
title ERP & POS System - Universal One-Click Launcher (Port 1741)
cd /d "%~dp0"

echo ======================================================================
echo    ERP & POS System — One-Click Universal Launcher (Windows)
echo    Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)
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
        echo PowerShell 5.1+ diperlukan untuk menjalankan instalasi otomatis.
        pause
        exit /b 1
    )
)

:: 2. Jalankan Installer PowerShell dengan Bypass Execution Policy
if exist "%~dp0scripts\install.ps1" (
    "%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install.ps1" %*
) else (
    echo [ERROR] Berkas scripts\install.ps1 tidak ditemukan di %~dp0
    pause
    exit /b 1
)

set "EXIT_CODE=%ERRORLEVEL%"
if %EXIT_CODE% neq 0 (
    echo.
    echo ======================================================================
    echo [ERROR] Eksekusi installer selesai dengan kode error: %EXIT_CODE%
    echo Silakan periksa log atau pesan di atas untuk mengatasi kendala.
    echo ======================================================================
    echo Tekan tombol apa saja untuk menutup jendela ini...
    pause >nul
)

exit /b %EXIT_CODE%
