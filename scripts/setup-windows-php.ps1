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
$cacertFile = Join-Path $destDir 'cacert.pem'

if (-not (Test-Path $toolsDir)) {
    New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null
}

# ------------------------------------------------------------------------------
# 1. Periksa & Pasang Microsoft Visual C++ 2015-2022 Redistributable (x64)
# ------------------------------------------------------------------------------
Write-Host "[1/4] Memeriksa dependensi sistem Visual C++ Runtime..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host "Mengunduh & memasang Visual C++ Redistributable (diperlukan untuk PHP Windows)..." -ForegroundColor Yellow
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$vcUrl" -o "$vcFile" 2>$null
        } else {
            (New-Object System.Net.WebClient).DownloadFile($vcUrl, $vcFile)
        }
        
        if (Test-Path $vcFile) {
            # Coba jalankan secara pasif
            $p = Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -PassThru -Wait
            if ($p.ExitCode -ne 0 -and $p.ExitCode -ne 3010) {
                # Jika memerlukan elevasi UAC
                Write-Host "Meminta izin Administrator untuk menyelesaikan instalasi Visual C++..." -ForegroundColor Yellow
                Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait
            }
            Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
            Write-Host "✔ Visual C++ Redistributable terpasang." -ForegroundColor Green
        }
    } catch {
        Write-Host "⚠️ Peringatan saat memasang Visual C++: $_" -ForegroundColor Yellow
    }
} else {
    Write-Host "✔ Visual C++ Redistributable sudah aktif." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 2. Unduh Paket PHP 8.4 Portable Resmi (Thread Safe x64)
# ------------------------------------------------------------------------------
Write-Host "[2/4] Mengunduh paket resmi PHP 8.4 Windows x64..." -ForegroundColor Cyan

$urls = @(
    "https://downloads.php.net/~windows/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
    "https://downloads.php.net/~windows/releases/php-8.4.27-1-Win32-vs17-x64.zip"
)

function Get-PhpPackage([string]$url, [string]$target) {
    Write-Host "Mengunduh: $url ..." -ForegroundColor Gray
    
    # Prioritas 1: System.Net.WebClient (.NET Native Windows, stabil)
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64)")
        $wc.DownloadFile($url, $target)
        if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
            return $true
        }
    } catch {}

    # Prioritas 2: curl.exe bawaan Windows 10/11
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
    Write-Host "❌ Gagal mengunduh PHP otomatis. Pastikan komputer terhubung ke internet." -ForegroundColor Red
    exit 1
}

# ------------------------------------------------------------------------------
# 3. Ekstrak PHP & Siapkan Runtime Portable Mandiri
# ------------------------------------------------------------------------------
Write-Host "[3/4] Mengekstrak paket PHP dan modul dependensi..." -ForegroundColor Cyan

# Hentikan proses PHP yang mungkin masih mengunci folder tools\php
Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object {
    try { $_.Path -like "*$destDir*" } catch { $false }
} | Stop-Process -Force -ErrorAction SilentlyContinue

if (Test-Path $destDir) {
    Remove-Item -Path $destDir -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Path $destDir -Force | Out-Null

Expand-Archive -Path $zipPath -DestinationPath $destDir -Force
Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue

# Salin VCRuntime DLLs langsung ke dalam folder tools\php (Portable murni tanpa dependensi System32)
$dllsToBundle = @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll', 'msvcp140_1.dll', 'msvcp140_2.dll', 'msvcp140_atomic_wait.dll')
foreach ($dll in $dllsToBundle) {
    $src = Join-Path $system32 $dll
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $destDir -Force -ErrorAction SilentlyContinue
    }
}

# Fallback pencarian DLL runtime di folder aplikasi jika di System32 belum ada
if (-not (Test-Path (Join-Path $destDir 'vcruntime140.dll'))) {
    $altPaths = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application",
        "$env:ProgramFiles\Microsoft\Edge\Application",
        "$env:ProgramFiles\Google\Chrome\Application"
    )
    foreach ($ap in $altPaths) {
        if (Test-Path $ap) {
            $found = Get-ChildItem -Path $ap -Filter "vcruntime140.dll" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($found) {
                $pDir = $found.DirectoryName
                foreach ($dll in @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll')) {
                    $s = Join-Path $pDir $dll
                    if (Test-Path $s) {
                        Copy-Item -Path $s -Destination $destDir -Force -ErrorAction SilentlyContinue
                    }
                }
                break
            }
        }
    }
}

# Unduh SSL CA Certificate Bundle (cacert.pem) agar HTTPS/cURL & Composer aman di Windows
try {
    $cacertUrl = "https://curl.se/ca/cacert.pem"
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -sS -L --fail "$cacertUrl" -o "$cacertFile" 2>$null
    } else {
        (New-Object System.Net.WebClient).DownloadFile($cacertUrl, $cacertFile)
    }
} catch {
    # Non-kritis jika gagal
}

# ------------------------------------------------------------------------------
# 4. Konfigurasi php.ini: Menghilangkan Komentar (;) & Mengaktifkan Modul
# ------------------------------------------------------------------------------
Write-Host "[4/4] Mengonfigurasi php.ini (mengaktifkan modul pdo_mysql, sqlite, curl, opcache, dll)..." -ForegroundColor Cyan

$iniDev = Join-Path $destDir 'php.ini-development'
$iniTarget = Join-Path $destDir 'php.ini'

if (Test-Path $iniDev) {
    $c = Get-Content $iniDev -Raw

    # A. Tentukan direktori ekstensi menggunakan ABSOLUTE PATH (krusial agar tidak error saat cd folder)
    $extPathFormatted = (Join-Path $destDir 'ext').Replace('\', '/')
    $c = $c -replace '(?m)^[;\s]*extension_dir\s*=\s*"ext"', "extension_dir = `"$extPathFormatted`""

    # B. Hilangkan tanda titik koma (;) pada ekstensi-ekstensi wajib Laravel & ERP
    $extList = @(
        'curl',
        'fileinfo',
        'gd',
        'intl',
        'mbstring',
        'mysqli',
        'openssl',
        'pdo_mysql',
        'pdo_sqlite',
        'sqlite3',
        'zip'
    )
    foreach ($ext in $extList) {
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, "(?m)^[;\s]*extension\s*=\s*$ext\b", "extension=$ext")
    }

    # C. Konfigurasi Zend OPcache (Wajib untuk performa optimal Laravel 13)
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*zend_extension\s*=\s*opcache\b', 'zend_extension=opcache')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*opcache\.enable\s*=\s*1\b', 'opcache.enable=1')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*opcache\.enable_cli\s*=\s*0\b', 'opcache.enable_cli=1')

    # D. Sesuaikan Resource Limit untuk Composer & Laravel Migration
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^memory_limit\s*=\s*\d+M', 'memory_limit = 512M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^max_execution_time\s*=\s*\d+', 'max_execution_time = 300')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^post_max_size\s*=\s*\d+M', 'post_max_size = 64M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^upload_max_filesize\s*=\s*\d+M', 'upload_max_filesize = 64M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*date\.timezone\s*=', 'date.timezone = Asia/Jakarta')

    # E. Sambungkan CA Certificate jika tersedia
    if (Test-Path $cacertFile) {
        $cacertFormatted = $cacertFile.Replace('\', '/')
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*curl\.cainfo\s*=', "curl.cainfo = `"$cacertFormatted`"")
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*openssl\.cafile\s*=', "openssl.cafile = `"$cacertFormatted`"")
    }

    # F. Tambahkan blok pengaman di baris paling bawah untuk memastikan tidak ada konfigurasi terlewat
    $extraConfig = @"

;; ==============================================================================
;; ERP & POS System Mandatory Overrides
;; ==============================================================================
[ErpPos]
extension_dir = "$extPathFormatted"
memory_limit = 512M
max_execution_time = 300
date.timezone = Asia/Jakarta
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
"@
    $c += $extraConfig

    Set-Content -Path $iniTarget -Value $c -Encoding UTF8
}

# ------------------------------------------------------------------------------
# 5. Verifikasi Fungsionalitas PHP Binary & Modul Ekstensi
# ------------------------------------------------------------------------------
$phpExe = Join-Path $destDir 'php.exe'
if (Test-Path $phpExe) {
    try {
        $ver = & $phpExe -r "echo PHP_VERSION;" 2>&1
        if ($ver -and ($ver -notmatch 'Error') -and ($ver -notmatch 'Warning') -and ($ver -match '^\d+\.\d+')) {
            Write-Host "✔ PHP Portable ($ver) berhasil dikonfigurasi & aktif!" -ForegroundColor Green
            $activeMods = & $phpExe -m 2>$null
            $reqMods = @('pdo_mysql', 'pdo_sqlite', 'curl', 'mbstring', 'openssl', 'zip')
            $okCount = 0
            foreach ($m in $reqMods) {
                if ($activeMods -match "(?i)\b$m\b") { $okCount++ }
            }
            Write-Host "✔ Modul kritis terverifikasi aktif ($okCount/$($reqMods.Count)) di php.ini" -ForegroundColor Green
            exit 0
        } else {
            Write-Host "❌ PHP diekstrak tetapi menghasilkan error saat dieksekusi: $ver" -ForegroundColor Red
            exit 1
        }
    } catch {
        Write-Host "❌ Gagal memanggil php.exe: $_" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "❌ File php.exe tidak ditemukan di $destDir" -ForegroundColor Red
    exit 1
}
