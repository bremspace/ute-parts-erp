<#
.SYNOPSIS
    Builds the complete Windows Desktop installer package on Windows.
#>

param(
    [string]$Version = "1.0.0"
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$WindowsDir = Split-Path -Parent $ScriptDir
$RootDir = Split-Path -Parent (Split-Path -Parent $WindowsDir)
$PayloadDir = Join-Path $WindowsDir "payload"
$DistDir = Join-Path $RootDir "dist\windows"

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host " Packaging Bremspace ERP Desktop v$Version for Windows" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

New-Item -ItemType Directory -Force -Path $PayloadDir | Out-Null
New-Item -ItemType Directory -Force -Path $DistDir | Out-Null

# 1. Download binaries if missing
if (-not (Test-Path (Join-Path $WindowsDir "binaries\frankphp")) -or -not (Test-Path (Join-Path $WindowsDir "binaries\mariadb"))) {
    & (Join-Path $WindowsDir "scripts\download-binaries.ps1")
}

# 2. Build Vite Assets
Write-Host "==> Building frontend assets with Vite..." -ForegroundColor Cyan
Push-Location $RootDir
npm run build
Pop-Location

# 3. Assemble Application Payload
Write-Host "==> Copying application files..." -ForegroundColor Cyan
$AppDest = Join-Path $PayloadDir "app"
if (Test-Path $AppDest) { Remove-Item -Recurse -Force $AppDest }
New-Item -ItemType Directory -Force -Path $AppDest | Out-Null

robocopy $RootDir $AppDest /MIR /XD .git node_modules tests tools dist /XF *.log /NDL /NFL /NJH /NJS | Out-Null

# 4. Copy Engines
Write-Host "==> Copying FrankPHP & MariaDB portable engines..." -ForegroundColor Cyan
$EngineDest = Join-Path $PayloadDir "engine"
$DbDest = Join-Path $PayloadDir "db_engine"
$WebviewDest = Join-Path $PayloadDir "webview"

if (Test-Path $EngineDest) { Remove-Item -Recurse -Force $EngineDest }
if (Test-Path $DbDest) { Remove-Item -Recurse -Force $DbDest }
if (Test-Path $WebviewDest) { Remove-Item -Recurse -Force $WebviewDest }

Copy-Item (Join-Path $WindowsDir "binaries\frankphp") -Destination $EngineDest -Recurse -Force
Copy-Item (Join-Path $WindowsDir "binaries\mariadb") -Destination $DbDest -Recurse -Force
New-Item -ItemType Directory -Force -Path $WebviewDest | Out-Null
Copy-Item (Join-Path $WindowsDir "binaries\webview2\MicrosoftEdgeWebview2Setup.exe") -Destination $WebviewDest -Force

# 5. Compile Launcher (Go)
Write-Host "==> Compiling launcher.exe..." -ForegroundColor Cyan
if (Get-Command go -ErrorAction SilentlyContinue) {
    Push-Location (Join-Path $WindowsDir "launcher")
    $env:GOOS = "windows"
    $env:GOARCH = "amd64"
    go build -ldflags "-H windowsgui -s -w" -o (Join-Path $PayloadDir "launcher.exe") main.go
    Pop-Location
} else {
    Write-Warning "Go not found. Copying launcher.ps1 as fallback."
    Copy-Item (Join-Path $WindowsDir "launcher\launcher.ps1") -Destination (Join-Path $PayloadDir "launcher.ps1") -Force
}

# 6. Inno Setup Compilation
Write-Host "==> Compiling installer with Inno Setup..." -ForegroundColor Cyan
$Iscc = "C:\Program Files (x86)\Inno Setup 6\ISCC.exe"
if (Test-Path $Iscc) {
    & $Iscc "/DAppVersion=$Version" (Join-Path $WindowsDir "installer.iss")
    Write-Host "==> Setup installer created in: $DistDir" -ForegroundColor Green
} else {
    Write-Warning "Inno Setup (ISCC.exe) not found at standard path. Payload assembled for CI/CD."
}
