@echo off
setlocal enabledelayedexpansion

:: 1. Cek port 20128 (9router)
netstat -ano | findstr /r /c:":20128 " >nul 2>nul
if errorlevel 1 (
    echo [INFO] Menyalakan 9router di background pada port 20128...
    start "" /b 9router -p 20128 --skip-update >nul 2>nul
    timeout /t 1 /nobreak >nul
)

:: 2. Jalankan opencode
where opencode >nul 2>nul
if %errorlevel% equ 0 (
    opencode %*
) else (
    echo [ERROR] OpenCode belum terpasang di PATH.
    echo Pasang via npm: npm install -g opencode-ai 9router codebase-memory-mcp oh-my-opencode
    pause
)
