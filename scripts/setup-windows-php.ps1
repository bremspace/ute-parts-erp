# ==============================================================================
# ERP & POS System - Windows PHP 8.5 & VC++ Portable Auto-Installer
# ==============================================================================
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir
$toolsDir = Join-Path $projectRoot 'tools'
$destDir = Join-Path $toolsDir 'php'
$zipPath = Join-Path $toolsDir 'php.zip'
$vcFile = Join-Path $toolsDir 'vc_redist.x64.exe'

if (-not (Test-Path $toolsDir)) {
    New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null
}

# 1. Periksa Microsoft Visual C++ 2015-2022 Redistributable (vcruntime140.dll & msvcp140.dll)
Write-Host "[1/3] Memeriksa Microsoft Visual C++ Redistributable (vcredist)..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host "Mengunduh & memasang Microsoft Visual C++ 2015-2022 Redistributable..." -ForegroundColor Yellow
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $vcUrl -OutFile $vcFile -TimeoutSec 90
        Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -Wait
        Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
        Write-Host "Visual C++ Redistributable berhasil dipasang." -ForegroundColor Green
    } catch {
        Write-Warning "Gagal memasang Visual C++ otomatis: $($_.Exception.Message)"
    }
} else {
    Write-Host "Visual C++ Redistributable sudah terpasang." -ForegroundColor Green
}

# 2. Unduh PHP Portable (prioritaskan 8.5, fallback 8.4)
Write-Host "[2/3] Mengunduh PHP Portable 8.5 resmi dari windows.php.net..." -ForegroundColor Cyan
$urls = @(
    "https://windows.php.net/downloads/releases/php-8.5.12-1-Win32-vs17-x64.zip",
    "https://windows.php.net/downloads/releases/php-8.4.27-1-Win32-vs17-x64.zip",
    "https://windows.php.net/downloads/releases/archives/php-8.4.27-1-Win32-vs17-x64.zip",
    "https://windows.php.net/downloads/releases/archives/php-8.5.12-1-Win32-vs17-x64.zip"
)

$downloaded = $false
foreach ($u in $urls) {
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Write-Host "Mencoba: $u"
        Invoke-WebRequest -Uri $u -OutFile $zipPath -TimeoutSec 120
        if ((Get-Item $zipPath).Length -gt 1000000) {
            $downloaded = $true
            break
        }
    } catch {
        # Coba url berikutnya
    }
}

if (-not $downloaded) {
    Write-Warning "Gagal mengunduh PHP otomatis dari windows.php.net. Silakan gunakan Laragon atau install PHP manual."
    exit 1
}

# 3. Ekstrak & Konfigurasi php.ini
Write-Host "[3/3] Mengekstrak dan mengonfigurasi ekstensi PHP..." -ForegroundColor Cyan
if (Test-Path $destDir) {
    Remove-Item -Path $destDir -Recurse -Force -ErrorAction SilentlyContinue
}
Expand-Archive -Path $zipPath -DestinationPath $destDir -Force
Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue

$iniDev = Join-Path $destDir 'php.ini-development'
$iniTarget = Join-Path $destDir 'php.ini'

if (Test-Path $iniDev) {
    $c = Get-Content $iniDev -Raw
    $c = $c -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
    $c = $c -replace ';extension=curl', 'extension=curl'
    $c = $c -replace ';extension=fileinfo', 'extension=fileinfo'
    $c = $c -replace ';extension=gd', 'extension=gd'
    $c = $c -replace ';extension=mbstring', 'extension=mbstring'
    $c = $c -replace ';extension=openssl', 'extension=openssl'
    $c = $c -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
    $c = $c -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite'
    $c = $c -replace ';extension=sqlite3', 'extension=sqlite3'
    $c = $c -replace ';extension=intl', 'extension=intl'
    $c = $c -replace ';extension=zip', 'extension=zip'
    $c = $c -replace ';extension=bcmath', 'extension=bcmath'
    $c = $c -replace ';zend_extension=opcache', 'zend_extension=opcache'
    if ($c -notmatch '(?m)^\s*zend_extension\s*=\s*opcache') {
        $c += "`r`nzend_extension=opcache`r`nopcache.enable=1`r`nopcache.enable_cli=1`r`nopcache.memory_consumption=128`r`n"
    }
    Set-Content -Path $iniTarget -Value $c
}

Write-Host "PHP Portable siap di: $destDir\php.exe" -ForegroundColor Green
exit 0
