# ==============================================================================
# ERP & POS System - Windows PHP 8.4/8.5 & VC++ Portable Auto-Installer
# ==============================================================================
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

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
Write-Host "[1/3] Memeriksa dependensi sistem Visual C++..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host "Mengunduh & memasang Visual C++ Redistributable (sekali saja)..." -ForegroundColor Yellow
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -s -L $vcUrl -o $vcFile
        } else {
            Invoke-WebRequest -Uri $vcUrl -OutFile $vcFile -TimeoutSec 120
        }
        if (Test-Path $vcFile) {
            Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -Wait
            Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
            Write-Host "✔ Visual C++ Redistributable terpasang." -ForegroundColor Green
        }
    } catch {
        # Abaikan jika user bukan administrator atau gagal otomatis
    }
} else {
    Write-Host "✔ Visual C++ Redistributable sudah aktif." -ForegroundColor Green
}

# 2. Unduh PHP Portable Resmi (Langsung dari mirror downloads.php.net tanpa trial & error berulang)
Write-Host "[2/3] Mengunduh paket PHP Portable Windows resmi..." -ForegroundColor Cyan

# Ambil URL rilis terbaru secara cerdas via releases.json jika memungkinkan, atau URL direct
$directUrls = @(
    "https://downloads.php.net/~windows/releases/php-8.4.27-1-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/php-8.5.12-1-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/php-8.4.27-1-nts-Win32-vs17-x64.zip"
)

$downloadUrl = ""
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $releasesJson = Invoke-RestMethod -Uri "https://downloads.php.net/~windows/releases/releases.json" -TimeoutSec 15
    if ($releasesJson.'8.4'.'ts-vs17-x64'.zip.path) {
        $downloadUrl = "https://downloads.php.net/~windows/releases/" + $releasesJson.'8.4'.'ts-vs17-x64'.zip.path
    } elseif ($releasesJson.'8.5'.'ts-vs17-x64'.zip.path) {
        $downloadUrl = "https://downloads.php.net/~windows/releases/" + $releasesJson.'8.5'.'ts-vs17-x64'.zip.path
    }
} catch {
    # Fallback ke direct list
}

if (-not $downloadUrl) {
    $downloadUrl = $directUrls[0]
}

$downloaded = $false
try {
    Write-Host "Mengunduh PHP dari sumber resmi: $downloadUrl" -ForegroundColor Gray
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -L -# -o $zipPath $downloadUrl
    } else {
        Invoke-WebRequest -Uri $downloadUrl -OutFile $zipPath -TimeoutSec 180
    }
    if ((Test-Path $zipPath) -and ((Get-Item $zipPath).Length -gt 5000000)) {
        $downloaded = $true
    }
} catch {
    # Fallback ke cadangan
}

if (-not $downloaded) {
    foreach ($fb in $directUrls) {
        if ($fb -eq $downloadUrl) { continue }
        try {
            Write-Host "Mencoba mirror cadangan: $fb" -ForegroundColor Gray
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -L -# -o $zipPath $fb
            } else {
                Invoke-WebRequest -Uri $fb -OutFile $zipPath -TimeoutSec 180
            }
            if ((Test-Path $zipPath) -and ((Get-Item $zipPath).Length -gt 5000000)) {
                $downloaded = $true
                break
            }
        } catch {}
    }
}

if (-not $downloaded) {
    Write-Host "❌ Gagal mengunduh PHP otomatis. Pastikan koneksi internet aktif." -ForegroundColor Red
    exit 1
}

# 3. Ekstrak & Konfigurasi Ekstensi Wajib di php.ini
Write-Host "[3/3] Mengekstrak & mengaktifkan modul database serta ekstensi PHP..." -ForegroundColor Cyan
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

Write-Host "✔ PHP Portable siap digunakan!" -ForegroundColor Green
exit 0
