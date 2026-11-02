# ==============================================================================
# ERP & POS System - Windows PHP 8.4 Portable & VC++ Auto-Installer
# ==============================================================================
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
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
            & curl.exe -sS -L --fail "$vcUrl" -o "$vcFile" 2>$null
        } else {
            (New-Object System.Net.WebClient).DownloadFile($vcUrl, $vcFile)
        }
        if (Test-Path $vcFile) {
            Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -Wait
            Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
            Write-Host "✔ Visual C++ Redistributable terpasang." -ForegroundColor Green
        }
    } catch {
        # Abaikan jika izin administrator tidak tersedia atau gagal pasif
    }
} else {
    Write-Host "✔ Visual C++ Redistributable sudah aktif." -ForegroundColor Green
}

# 2. Unduh PHP Portable Resmi (PHP 8.4 x64 Thread Safe)
Write-Host "[2/3] Mengunduh paket PHP 8.4 Windows resmi..." -ForegroundColor Cyan

$urls = @(
    "https://downloads.php.net/~windows/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
    "https://downloads.php.net/~windows/releases/php-8.4.27-1-Win32-vs17-x64.zip"
)

function Get-PhpPackage([string]$url, [string]$target) {
    Write-Host "Mengunduh dari: $url ..." -ForegroundColor Gray
    
    # Prioritas 1: System.Net.WebClient (Standar .NET, native Windows, tanpa isu stderr)
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64)")
        $wc.DownloadFile($url, $target)
        if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
            return $true
        }
    } catch {}

    # Prioritas 2: curl.exe bawaan Windows 10/11 (-sS silent tanpa stderr progress bar)
    try {
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$url" -o "$target" 2>$null
            if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
                return $true
            }
        }
    } catch {}

    # Prioritas 3: Invoke-WebRequest
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $target -UseBasicParsing -TimeoutSec 180
        if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
            return $true
        }
    } catch {}

    return $false
}

$downloaded = $false
foreach ($u in $urls) {
    if (Get-PhpPackage -url $u -target $zipPath) {
        $downloaded = $true
        break
    }
}

if (-not $downloaded) {
    Write-Host "❌ Gagal mengunduh PHP otomatis. Pastikan koneksi internet aktif." -ForegroundColor Red
    exit 1
}

# 3. Ekstrak & Konfigurasi Ekstensi Wajib di php.ini
Write-Host "[3/3] Mengekstrak & mengaktifkan modul database serta ekstensi PHP..." -ForegroundColor Cyan

# Hentikan proses php lama jika masih ada
Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object {
    try { $_.Path -like "*$destDir*" } catch { $false }
} | Stop-Process -Force -ErrorAction SilentlyContinue

if (Test-Path $destDir) {
    Remove-Item -Path $destDir -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Path $destDir -Force | Out-Null

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
    $c = $c -replace ';extension=intl', 'extension=intl'
    $c = $c -replace ';extension=mbstring', 'extension=mbstring'
    $c = $c -replace ';extension=mysqli', 'extension=mysqli'
    $c = $c -replace ';extension=openssl', 'extension=openssl'
    $c = $c -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
    $c = $c -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite'
    $c = $c -replace ';extension=sqlite3', 'extension=sqlite3'
    $c = $c -replace ';extension=zip', 'extension=zip'
    $c = $c -replace ';zend_extension=opcache', 'zend_extension=opcache'

    if ($c -notmatch '(?m)^\s*opcache\.enable\s*=\s*1') {
        $c += "`r`n[opcache]`r`nzend_extension=opcache`r`nopcache.enable=1`r`nopcache.enable_cli=1`r`nopcache.memory_consumption=128`r`n"
    }
    Set-Content -Path $iniTarget -Value $c -Encoding UTF8
}

$phpExe = Join-Path $destDir 'php.exe'
if (Test-Path $phpExe) {
    $ver = & $phpExe -r "echo PHP_VERSION;" 2>$null
    Write-Host "✔ PHP Portable ($ver) siap digunakan!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "❌ File php.exe tidak ditemukan di $destDir" -ForegroundColor Red
    exit 1
}
