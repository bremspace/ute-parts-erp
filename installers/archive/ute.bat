@echo off
setlocal

:: ==============================================================================
:: Shortcut Ute Parts ERP + OpenCode untuk Windows
:: ==============================================================================

:: Folder project (otomatis deteksi direktori tempat file bat ini berada)
set "PROJECT_DIR=%~dp0"
cd /d "%PROJECT_DIR%"

title Ute Parts - OpenCode Workspace

where opencode >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] OpenCode belum terpasang atau belum ada di PATH.
    echo Pasang via: npm install -g opencode-ai
    pause
    exit /b 1
)

echo ====================================================
echo   Membuka OpenCode Workspace: Ute Parts ERP
echo   Direktori: %CD%
echo ====================================================
echo.

opencode
