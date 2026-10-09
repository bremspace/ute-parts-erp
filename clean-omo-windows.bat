@echo off
setlocal enabledelayedexpansion

echo ====================================================
echo   PEMBERSIH OMO BIASA - AKTIFKAN OMO-SLIM MURNI
echo ====================================================
echo.

set "CONFIG_DIR=%USERPROFILE%\.config\opencode"
set "LOCAL_DIR=%USERPROFILE%\.local\share"
set "CACHE_DIR=%USERPROFILE%\.cache"

echo [1/3] Menghapus cache dan direktori OMO biasa...
if exist "%LOCAL_DIR%\oh-my-opencode" (
    rd /s /q "%LOCAL_DIR%\oh-my-opencode" 2>nul
    echo   - Dihapus: %LOCAL_DIR%\oh-my-opencode
)
if exist "%CACHE_DIR%\oh-my-opencode" (
    rd /s /q "%CACHE_DIR%\oh-my-opencode" 2>nul
    echo   - Dihapus: %CACHE_DIR%\oh-my-opencode
)
if exist "%CACHE_DIR%\opencode\packages\oh-my-openagent@latest" (
    rd /s /q "%CACHE_DIR%\opencode\packages\oh-my-openagent@latest" 2>nul
    echo   - Dihapus: cache package oh-my-openagent
)
if exist "%LOCAL_DIR%\opencode\storage\oh-my-openagent" (
    rd /s /q "%LOCAL_DIR%\opencode\storage\oh-my-openagent" 2>nul
    echo   - Dihapus: storage oh-my-openagent
)

echo [2/3] Memperbarui konfigurasi plugin OpenCode ke OMO-Slim murni...

:: Tulis ulang tui.json murni OMO-Slim
(
    echo ["oh-my-opencode-slim"]
) > "%CONFIG_DIR%\tui.json"
echo   - Diperbarui: %CONFIG_DIR%\tui.json

:: Tulis ulang cli.json murni OMO-Slim
(
    echo {
    echo   "$schema": "https://opencode.ai/v2/cli.json",
    echo   "plugins": [
    echo     "oh-my-opencode-slim"
    echo   ]
    echo }
) > "%CONFIG_DIR%\cli.json"
echo   - Diperbarui: %CONFIG_DIR%\cli.json

:: Perbaiki opencode.jsonc jika ada
if exist "%CONFIG_DIR%\opencode.jsonc" (
    powershell -Command "(Get-Content '%CONFIG_DIR%\opencode.jsonc') -replace 'oh-my-openagent@latest', 'oh-my-opencode-slim' | Set-Content '%CONFIG_DIR%\opencode.jsonc'"
    echo   - Dibersihkan: %CONFIG_DIR%\opencode.jsonc
)

:: Perbaiki opencode.json jika ada
if exist "%CONFIG_DIR%\opencode.json" (
    powershell -Command "(Get-Content '%CONFIG_DIR%\opencode.json') -replace 'oh-my-openagent@latest', '' | Set-Content '%CONFIG_DIR%\opencode.json'"
    echo   - Dibersihkan: %CONFIG_DIR%\opencode.json
)

echo [3/3] Memverifikasi OMO-Slim...
if exist "%CONFIG_DIR%\oh-my-opencode-slim.json" (
    echo   - OK: Konfigurasi OMO-Slim terdeteksi.
) else (
    echo   - PERINGATAN: File oh-my-opencode-slim.json belum ada di %CONFIG_DIR%.
)

echo.
echo ====================================================
echo   SELESAI! OMO BIASA TELAH DIBERSIHKAN DARI WINDOWS
echo ====================================================
echo Sekarang jalankan kembali:
echo   opencode
echo.
pause
