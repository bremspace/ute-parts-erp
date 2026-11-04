# ==============================================================================
# ERP and POS System - Windows PHP 8.4 Portable and Runtime Auto-Installer
# ==============================================================================
$ProgressPreference = 'SilentlyContinue'

# Enable TLS 1.2 and TLS 1.3 for secure web downloads
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13
} catch {}

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

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "   Penyiapan Otomatis PHP 8.4 Portable dan Runtime Windows" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# 1. Periksa dan Pasang Microsoft Visual C++ 2015-2022 Redistributable (x64)
# ------------------------------------------------------------------------------
Write-Host "[1/4] Memeriksa Visual C++ Runtime (vcruntime140.dll dan msvcp140.dll)..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host "Mengunduh Visual C++ Redistributable resmi Microsoft..." -ForegroundColor Yellow
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    try {
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$vcUrl" -o "$vcFile" 2>$null
        } else {
            $wc = New-Object System.Net.WebClient
            $wc.DownloadFile($vcUrl, $vcFile)
        }
        
        if ((Test-Path $vcFile) -and ((Get-Item $vcFile).Length -gt 1000000)) {
            Write-Host "Memasang Visual C++ Redistributable di background..." -ForegroundColor Gray
            $p = Start-Process -FilePath $vcFile -ArgumentList '/install', '/quiet', '/norestart' -PassThru -Wait
            if ($p.ExitCode -ne 0 -and $p.ExitCode -ne 3010) {
                Start-Process -FilePath $vcFile -ArgumentList '/install', '/passive', '/norestart' -PassThru -Wait -ErrorAction SilentlyContinue
            }
            Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
        }
    } catch {
        Write-Host "Catatan: Instalasi sistem VC++ dilewati, akan menggunakan fallback bundling lokal." -ForegroundColor Gray
    }
} else {
    Write-Host "[OK] Visual C++ Runtime sistem terdeteksi aktif." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 2. Unduh Paket PHP 8.4 Portable Resmi (Thread Safe x64)
# ------------------------------------------------------------------------------
Write-Host "[2/4] Mengunduh paket resmi PHP 8.4 Windows x64..." -ForegroundColor Cyan

$urls = @(
    "https://downloads.php.net/~windows/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
    "https://windows.php.net/downloads/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
    "https://downloads.php.net/~windows/releases/archives/php-8.4.4-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/archives/php-8.4.3-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/archives/php-8.4.2-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/archives/php-8.4.1-Win32-vs17-x64.zip",
    "https://downloads.php.net/~windows/releases/archives/php-8.4.0-Win32-vs17-x64.zip"
)

function Download-FileWithFallback([string]$url, [string]$target) {
    Write-Host "Mengunduh dari: $url ..." -ForegroundColor Gray
    
    try {
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$url" -o "$target" 2>$null
            if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
                return $true
            }
        }
    } catch {}

    try {
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64)")
        $wc.DownloadFile($url, $target)
        if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
            return $true
        }
    } catch {}

    try {
        Invoke-WebRequest -Uri $url -OutFile $target -UseBasicParsing -TimeoutSec 180 -ErrorAction SilentlyContinue
        if ((Test-Path $target) -and ((Get-Item $target).Length -gt 5000000)) {
            return $true
        }
    } catch {}

    return $false
}

$downloaded = $false
if ((Test-Path $zipPath) -and ((Get-Item $zipPath).Length -gt 25000000)) {
    Write-Host "Berkas arsip PHP sebelumnya ditemukan di tools\php.zip." -ForegroundColor Gray
    $downloaded = $true
} else {
    foreach ($u in $urls) {
        if (Download-FileWithFallback -url $u -target $zipPath) {
            $downloaded = $true
            break
        }
    }
}

if (-not $downloaded) {
    Write-Host "[ERROR] Gagal mengunduh paket PHP 8.4 otomatis." -ForegroundColor Red
    Write-Host "Pastikan koneksi internet aktif atau pasang PHP via: winget install PHP.PHP.8.4" -ForegroundColor Yellow
    exit 1
}

# ------------------------------------------------------------------------------
# 3. Ekstrak PHP dan Siapkan Runtime Portable Mandiri
# ------------------------------------------------------------------------------
Write-Host "[3/4] Mengekstrak paket PHP dan modul dependensi..." -ForegroundColor Cyan

Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object {
    try { $_.Path -like "*$destDir*" } catch { $false }
} | Stop-Process -Force -ErrorAction SilentlyContinue

if (-not (Test-Path $destDir)) {
    New-Item -ItemType Directory -Path $destDir -Force | Out-Null
}

try {
    Expand-Archive -Path $zipPath -DestinationPath $destDir -Force
    Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue
    Write-Host "[OK] Arsip PHP berhasil diekstrak." -ForegroundColor Green
} catch {
    Write-Host "Peringatan saat ekstrak zip: $_" -ForegroundColor Yellow
}

$dllsToBundle = @('vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll', 'msvcp140_1.dll', 'msvcp140_2.dll', 'msvcp140_atomic_wait.dll')
foreach ($dll in $dllsToBundle) {
    $src = Join-Path $system32 $dll
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $destDir -Force -ErrorAction SilentlyContinue
    }
}

if (-not (Test-Path (Join-Path $destDir 'vcruntime140.dll'))) {
    $altPaths = @(
        "$env:ProgramFiles\Microsoft\Edge\Application",
        "$env:ProgramFiles\Google\Chrome\Application",
        "$env:ProgramFiles\Microsoft VS Code",
        "$env:LOCALAPPDATA\Programs\Microsoft VS Code",
        (Join-Path $env:SystemRoot 'SysWOW64')
    )
    foreach ($ap in $altPaths) {
        if ($ap -and (Test-Path $ap)) {
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

try {
    $cacertUrl = "https://curl.se/ca/cacert.pem"
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -sS -L --fail "$cacertUrl" -o "$cacertFile" 2>$null
    } else {
        (New-Object System.Net.WebClient).DownloadFile($cacertUrl, $cacertFile)
    }
} catch {}

# ------------------------------------------------------------------------------
# 4. Konfigurasi php.ini: Menghilangkan Komentar (;) dan Mengaktifkan Modul
# ------------------------------------------------------------------------------
Write-Host "[4/4] Mengonfigurasi php.ini untuk Laravel 13 dan Bremspace ERP..." -ForegroundColor Cyan

$iniDev = Join-Path $destDir 'php.ini-development'
$iniProd = Join-Path $destDir 'php.ini-production'
$iniTarget = Join-Path $destDir 'php.ini'

$baseContent = ""
if (Test-Path $iniDev) {
    $baseContent = Get-Content $iniDev -Raw
} elseif (Test-Path $iniProd) {
    $baseContent = Get-Content $iniProd -Raw
}

$extPathFormatted = (Join-Path $destDir 'ext').Replace('\', '/')

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
    'zip',
    'bcmath',
    'exif'
)

if ($baseContent) {
    $c = $baseContent
    $c = $c -replace '(?m)^[;\s]*extension_dir\s*=\s*"ext"', "extension_dir = `"$extPathFormatted`""
    $c = $c -replace '(?m)^[;\s]*extension_dir\s*=\s*"./"', "extension_dir = `"$extPathFormatted`""
    foreach ($ext in $extList) {
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, "(?m)^[;\s]*extension\s*=\s*(php_)?$ext(\.dll)?\b", "extension=$ext")
    }
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*zend_extension\s*=\s*(php_)?opcache(\.dll)?\b', 'zend_extension=opcache')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*opcache\.enable\s*=\s*\d\b', 'opcache.enable=1')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*opcache\.enable_cli\s*=\s*\d\b', 'opcache.enable_cli=1')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*memory_limit\s*=\s*\d+M', 'memory_limit = 512M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*max_execution_time\s*=\s*\d+', 'max_execution_time = 300')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*post_max_size\s*=\s*\d+M', 'post_max_size = 64M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*upload_max_filesize\s*=\s*\d+M', 'upload_max_filesize = 64M')
    $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*date\.timezone\s*=', 'date.timezone = Asia/Jakarta')

    if (Test-Path $cacertFile) {
        $cacertFormatted = $cacertFile.Replace('\', '/')
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*curl\.cainfo\s*=', "curl.cainfo = `"$cacertFormatted`"")
        $c = [System.Text.RegularExpressions.Regex]::Replace($c, '(?m)^[;\s]*openssl\.cafile\s*=', "openssl.cafile = `"$cacertFormatted`"")
    }
} else {
    $c = "[PHP]`r`n"
}

# Pastikan semua ekstensi wajib terpasang di bawah [PHP]
$appendLines = @(
    "",
    ";; ==============================================================================",
    ";; Bremspace ERP Mandatory Extensions and Performance",
    ";; ==============================================================================",
    "extension_dir = `"$extPathFormatted`"",
    "extension=curl",
    "extension=fileinfo",
    "extension=gd",
    "extension=intl",
    "extension=mbstring",
    "extension=mysqli",
    "extension=openssl",
    "extension=pdo_mysql",
    "extension=pdo_sqlite",
    "extension=sqlite3",
    "extension=zip",
    "extension=bcmath",
    "extension=exif",
    "zend_extension=opcache",
    "memory_limit = 512M",
    "max_execution_time = 300",
    "post_max_size = 64M",
    "upload_max_filesize = 64M",
    "date.timezone = Asia/Jakarta",
    "opcache.enable = 1",
    "opcache.enable_cli = 1",
    "opcache.memory_consumption = 128"
)

$c += ($appendLines -join "`r`n")
Set-Content -Path $iniTarget -Value $c -Encoding ASCII
Write-Host "[OK] Berkas php.ini berhasil dibuat dan dikonfigurasi." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 5. Verifikasi Fungsionalitas PHP Binary dan Modul Ekstensi
# ------------------------------------------------------------------------------
$phpExe = Join-Path $destDir 'php.exe'
if (Test-Path $phpExe) {
    try {
        $ver = & $phpExe -r "echo PHP_VERSION;" 2>&1
        if ($ver -and ($ver -notmatch 'Error') -and ($ver -notmatch 'Warning') -and ($ver -match '^\d+\.\d+')) {
            Write-Host "[OK] PHP Portable ($ver) berhasil aktif dan terverifikasi!" -ForegroundColor Green
            # Log modul yang terpasang
            $mods = & $phpExe -m 2>&1
            $critical = @('pdo_mysql', 'pdo_sqlite', 'curl', 'mbstring', 'openssl', 'fileinfo')
            foreach ($m in $critical) {
                if ($mods -match "\b$m\b") {
                    Write-Host "  -> Ekstensi [$m] aktif." -ForegroundColor Green
                } else {
                    Write-Host "  -> Ekstensi [$m] belum terdeteksi di php -m" -ForegroundColor Yellow
                }
            }
            exit 0
        } else {
            Write-Host "[ERROR] PHP diekstrak tetapi runtime menghasilkan error: $ver" -ForegroundColor Red
            Write-Host "Kemungkinan dependensi Visual C++ 2015-2022 x64 belum terpasang." -ForegroundColor Yellow
            exit 1
        }
    } catch {
        Write-Host "[ERROR] Gagal memanggil php.exe: $_" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[ERROR] File php.exe tidak ditemukan di $destDir" -ForegroundColor Red
    exit 1
}
