@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Bremspace ERP - Unas 1-Click Installer
cd /d "%~dp0"

echo ======================================================================
echo    Bremspace ERP - Unas 1-Click Installer (Local Windows Edition)
echo    Otomatisasi Penuh: Cek PC, Dependensi, DB, Migrasi, Run
echo ======================================================================
echo.

rem 1. Pastikan file engine powershell ada
if not exist "%~dp0scripts\unas-setup.ps1" (
    echo [ERROR] File scripts\unas-setup.ps1 tidak ditemukan!
    echo Pastikan Anda mengekstrak seluruh isi folder proyek dengan lengkap.
    echo ======================================================================
    pause
    exit /b 1
)

rem 2. Deteksi binary PowerShell
set "POWERSHELL_BIN=powershell.exe"
where powershell.exe >nul 2>nul
if errorlevel 1 (
    if exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
        set "POWERSHELL_BIN=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
    ) else (
        echo [ERROR] Windows PowerShell tidak ditemukan di sistem ini.
        echo Silakan aktifkan fitur Windows PowerShell di PC Anda.
        echo ======================================================================
        pause
        exit /b 1
    )
)

rem 3. Jalankan installer dengan ExecutionPolicy Bypass
"%POWERSHELL_BIN%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\unas-setup.ps1" %*

set "EXIT_CODE=%ERRORLEVEL%"
echo.
if %EXIT_CODE% neq 0 (
    echo [INFO] Inisialisasi selesai dengan kode keluar: %EXIT_CODE%
    echo Silakan periksa pesan atau petunjuk solusi di atas.
) else (
    echo [SUKSES] Seluruh tahapan instalasi Bremspace ERP untuk Unas selesai!
)
echo ======================================================================
echo Tekan tombol apa saja untuk menutup jendela...
pause >nul
exit /b %EXIT_CODE%
