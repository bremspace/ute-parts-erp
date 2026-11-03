@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>nul
title Bremspace ERP & POS System - Universal Launcher (Port 1741)
cd /d "%~dp0"

echo ======================================================================
echo    Bremspace ERP & POS System — One-Click Universal Launcher
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
        pause
        exit /b 1
    )
)

:: 2. Prioritaskan Docker Engine (WSL2 Native / Desktop)
if exist "%~dp0scripts\install-docker.ps1" (
    "%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install-docker.ps1" %*
) else (
    :: Fallback ke installer PHP lokal
    "%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install.ps1" %*
)

set "EXIT_CODE=%ERRORLEVEL%"
if %EXIT_CODE% neq 0 (
    echo.
    echo ======================================================================
    echo [ERROR] Eksekusi installer selesai dengan kode: %EXIT_CODE%
    echo Silakan periksa pesan di atas untuk detail kendala.
    echo ======================================================================
    echo Tekan tombol apa saja untuk menutup jendela ini...
    pause >nul
)

exit /b %EXIT_CODE%
