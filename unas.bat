@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Bremspace ERP - Unas 1-Click Installer
cd /d "%~dp0"

echo ======================================================================
echo    Bremspace ERP - Unas 1-Click Installer (Local Windows Edition)
echo    Otomatisasi: Cek Kondisi PC, Dependensi, DB, Migrasi, Run
echo ======================================================================
echo.

rem 1. Pastikan PowerShell tersedia di Windows
set "POWERSHELL_BIN=powershell.exe"
where powershell.exe >nul 2>nul
if errorlevel 1 (
    if exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
        set "POWERSHELL_BIN=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
    ) else (
        echo [ERROR] Windows PowerShell tidak ditemukan di sistem ini.
        echo Silakan pastikan Windows PowerShell terpasang.
        echo ======================================================================
        pause
        exit /b 1
    )
)

rem 2. Jalankan engine installer PowerShell mandiri
"%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\unas-setup.ps1" %*

set "EXIT_CODE=%ERRORLEVEL%"
echo.
if %EXIT_CODE% neq 0 (
    echo [INFO] Inisialisasi selesai dengan status kode: %EXIT_CODE%
    echo Silakan periksa pesan atau panduan di atas jika ada dependensi yang perlu dilengkapi.
) else (
    echo [SUKSES] Seluruh tahapan instalasi Bremspace ERP untuk Unas selesai!
)
echo ======================================================================
echo Tekan tombol apa saja untuk keluar dari jendela ini...
pause >nul
exit /b %EXIT_CODE%
