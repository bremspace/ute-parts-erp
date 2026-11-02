# ==============================================================================
# ERP & POS System - Universal Windows Installer & Launcher
# PowerShell Engine (Rock-Solid, Zero Force-Close, Full Diagnostics)
# ==============================================================================
param(
    [switch]$ForceReinstallPhp
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

# Enable modern TLS
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13
} catch {}

$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
$projectRoot = Split-Path -Parent $scriptDir
Set-Location -Path $projectRoot

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "   ERP & POS System - Windows Universal Installer & Launcher" -ForegroundColor Cyan
Write-Host "   Lokasi Project: $projectRoot" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------------------------
# 0. Periksa Hak Administrator
# ------------------------------------------------------------------------------
$isAdmin = $false
try {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]$identity
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
} catch {}

if ($isAdmin) {
    Write-Host "[OK] Berjalan dengan Hak Akses Administrator." -ForegroundColor Green
} else {
    Write-Host "[INFO] Berjalan sebagai Standard User (Non-Elevated)." -ForegroundColor Yellow
}

# ------------------------------------------------------------------------------
# 1. Helper Fungsi Validasi PHP >= 8.4
# ------------------------------------------------------------------------------
function Test-PhpBinary([string]$binPath) {
    if (-not $binPath) { return $null }
    try {
        $cmd = $binPath
        if ($binPath -match '\s' -and -not ($binPath.StartsWith('"') -and $binPath.EndsWith('"'))) {
            $cmd = "`"$binPath`""
        }
        $out = & $binPath -r "echo PHP_VERSION;" 2>$null
        if ($out -and ($out -match '^(\d+)\.(\d+)')) {
            $maj = [int]$matches[1]
            $min = [int]$matches[2]
            if (($maj -eq 8 -and $min -ge 4) -or ($maj -gt 8)) {
                return [PSCustomObject]@{
                    Path = $binPath
                    Version = $out.Trim()
                }
            }
        }
    } catch {}
    return $null
}

# ------------------------------------------------------------------------------
# 2. Deteksi PHP Binary yang Sudah Ada
# ------------------------------------------------------------------------------
Write-Host "[1/6] Memeriksa ketersediaan PHP 8.4+ di sistem..." -ForegroundColor Cyan

$detectedPhp = $null

if (-not $ForceReinstallPhp) {
    # A. Cek tools\php lokal bawaan project
    $localPhp = Join-Path $projectRoot 'tools\php\php.exe'
    if (Test-Path $localPhp) {
        $detectedPhp = Test-PhpBinary $localPhp
    }

    # B. Cek System PATH
    if (-not $detectedPhp) {
        $pathPhp = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($pathPhp) {
            $detectedPhp = Test-PhpBinary $pathPhp.Source
        }
    }

    # C. Cek Winget / Program Files PHP
    if (-not $detectedPhp) {
        $candidates = @(
            "${env:ProgramFiles}\PHP\v8.4\php.exe",
            "${env:ProgramFiles}\PHP\php.exe",
            "${env:ProgramFiles(x86)}\PHP\php.exe"
        )
        $wingetPkg = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($wingetPkg) { $candidates += $wingetPkg.FullName }

        foreach ($c in $candidates) {
            if (Test-Path $c) {
                $detectedPhp = Test-PhpBinary $c
                if ($detectedPhp) { break }
            }
        }
    }

    # D. Cek Laragon (Drive C, D, E)
    if (-not $detectedPhp) {
        foreach ($drive in @('C', 'D', 'E')) {
            $laragonPath = "$drive`:\laragon\bin\php"
            if (Test-Path $laragonPath) {
                $dirs = Get-ChildItem -Path $laragonPath -Directory -Filter "php-8.*" -ErrorAction SilentlyContinue
                foreach ($d in $dirs) {
                    $exe = Join-Path $d.FullName 'php.exe'
                    if (Test-Path $exe) {
                        $detectedPhp = Test-PhpBinary $exe
                        if ($detectedPhp) { break }
                    }
                }
            }
            if ($detectedPhp) { break }
        }
    }

    # E. Cek XAMPP (Drive C, D, E)
    if (-not $detectedPhp) {
        foreach ($drive in @('C', 'D', 'E')) {
            $xamppPhp = "$drive`:\xampp\php\php.exe"
            if (Test-Path $xamppPhp) {
                $detectedPhp = Test-PhpBinary $xamppPhp
                if ($detectedPhp) { break }
            }
        }
    }
}

# ------------------------------------------------------------------------------
# 3. Instalasi PHP 8.4 Jika Belum Ada (Fokus Utama: Winget + Auto-Setup Winget)
# ------------------------------------------------------------------------------
if (-not $detectedPhp) {
    Write-Host "[INFO] PHP minimal 8.4 belum tersedia di komputer." -ForegroundColor Yellow
    Write-Host "[INFO] Menyiapkan PHP 8.4 via Windows Package Manager (winget)..." -ForegroundColor Cyan

    $wingetCmd = Get-Command winget.exe -ErrorAction SilentlyContinue

    # A. Jika winget belum ada di Windows, download & aktifkan secara otomatis!
    if (-not $wingetCmd) {
        Write-Host "ℹ Winget belum aktif di Windows ini. Mengunduh & memasang Winget resmi Microsoft..." -ForegroundColor Yellow
        $toolsDir = Join-Path $projectRoot 'tools'
        if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }
        
        $wingetBundle = Join-Path $toolsDir 'winget.msixbundle'
        $vclibsAppx = Join-Path $toolsDir 'vclibs.appx'
        
        try {
            Write-Host "Mengunduh VCLibs dependency..." -ForegroundColor Gray
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -sS -L --fail "https://aka.ms/Microsoft.VCLibs.x64.14.00.Desktop.appx" -o "$vclibsAppx" 2>$null
            } else {
                (New-Object System.Net.WebClient).DownloadFile("https://aka.ms/Microsoft.VCLibs.x64.14.00.Desktop.appx", $vclibsAppx)
            }
            if (Test-Path $vclibsAppx) {
                Add-AppxPackage -Path $vclibsAppx -ErrorAction SilentlyContinue
                Remove-Item -Path $vclibsAppx -Force -ErrorAction SilentlyContinue
            }

            Write-Host "Mengunduh Microsoft Desktop App Installer (Winget)..." -ForegroundColor Gray
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -sS -L --fail "https://aka.ms/getwinget" -o "$wingetBundle" 2>$null
            } else {
                (New-Object System.Net.WebClient).DownloadFile("https://aka.ms/getwinget", $wingetBundle)
            }
            if ((Test-Path $wingetBundle) -and ((Get-Item $wingetBundle).Length -gt 10000000)) {
                Add-AppxPackage -Path $wingetBundle -ErrorAction SilentlyContinue
                Remove-Item -Path $wingetBundle -Force -ErrorAction SilentlyContinue
                Write-Host "✔ Winget berhasil dipasang." -ForegroundColor Green
            }
        } catch {
            Write-Host "Peringatan saat penyiapan winget: $_" -ForegroundColor Yellow
        }

        # Cek ulang ketersediaan winget setelah instalasi
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        $wingetCmd = Get-Command winget.exe -ErrorAction SilentlyContinue
    }

    # B. Eksekusi Winget untuk memasang PHP 8.4
    if ($wingetCmd) {
        Write-Host "Menjalankan: winget install PHP.PHP.8.4 ..." -ForegroundColor Cyan
        try {
            & winget install --id PHP.PHP.8.4 -e --silent --accept-source-agreements --accept-package-agreements
        } catch {}

        # Refresh PATH lingkungan sesi
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        $pathPhp = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($pathPhp) {
            $detectedPhp = Test-PhpBinary $pathPhp.Source
        }

        # Cek folder instalasi standar winget PHP
        if (-not $detectedPhp) {
            $wingetChecks = @(
                "${env:ProgramFiles}\PHP\v8.4\php.exe",
                "${env:ProgramFiles}\PHP\php.exe"
            )
            foreach ($wc in $wingetChecks) {
                if (Test-Path $wc) {
                    $detectedPhp = Test-PhpBinary $wc
                    if ($detectedPhp) { break }
                }
            }
        }
    }

    # C. Fallback: Pasang PHP 8.4 Portable ke tools\php jika winget gagal atau Windows edisi lawas
    if (-not $detectedPhp) {
        Write-Host "[INFO] Menyiapkan PHP 8.4 Portable mandiri ke tools\php\..." -ForegroundColor Cyan
        $setupPs1 = Join-Path $scriptDir 'setup-windows-php.ps1'
        if (Test-Path $setupPs1) {
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $setupPs1
            $localPhp = Join-Path $projectRoot 'tools\php\php.exe'
            if (Test-Path $localPhp) {
                $detectedPhp = Test-PhpBinary $localPhp
            }
        }
    }
}

if (-not $detectedPhp) {
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host " [ERROR] PHP minimal 8.4 tidak berhasil disiapkan secara otomatis." -ForegroundColor Red
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host "Solusi Manual Cepat:" -ForegroundColor Yellow
    Write-Host "1. Buka Terminal / CMD lalu ketik: winget install PHP.PHP.8.4" -ForegroundColor White
    Write-Host "2. ATAU unduh & instal Laragon Full: https://laragon.org/download/" -ForegroundColor White
    Write-Host "3. ATAU unduh zip PHP 8.4 dari https://windows.php.net/download/ lalu ekstrak ke: $projectRoot\tools\php\" -ForegroundColor White
    Write-Host "======================================================================" -ForegroundColor Red
    exit 1
}

$phpExe = $detectedPhp.Path
$phpDir = Split-Path -Parent $phpExe
Write-Host "✔ PHP Terverifikasi: $phpExe (Versi: $($detectedPhp.Version))" -ForegroundColor Green

# Sisipkan folder PHP ke urutan pertama PATH sesi ini
$env:Path = "$phpDir;" + $env:Path

# ------------------------------------------------------------------------------
# 4. Validasi & Konfigurasi php.ini (Aktifkan Ekstensi Wajib)
# ------------------------------------------------------------------------------
Write-Host "[2/6] Memeriksa modul & ekstensi php.ini..." -ForegroundColor Cyan
$phpIni = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $phpIni)) {
    $iniDev = Join-Path $phpDir 'php.ini-development'
    $iniProd = Join-Path $phpDir 'php.ini-production'
    if (Test-Path $iniDev) {
        Copy-Item -Path $iniDev -Destination $phpIni -Force
    } elseif (Test-Path $iniProd) {
        Copy-Item -Path $iniProd -Destination $phpIni -Force
    } else {
        Set-Content -Path $phpIni -Value "[PHP]`r`n" -Encoding UTF8
    }
}

if (Test-Path $phpIni) {
    $iniContent = Get-Content -Path $phpIni -Raw
    $extDir = (Join-Path $phpDir 'ext').Replace('\', '/')
    $reqExts = @('curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'sqlite3', 'zip', 'bcmath', 'exif')
    
    $modified = $false
    if ($iniContent -notmatch "(?m)^\[ErpPos\]") {
        $append = @"

;; ==============================================================================
;; ERP & POS System Extensions & Performance
;; ==============================================================================
[ErpPos]
extension_dir = "$extDir"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=bcmath
extension=exif
memory_limit = 512M
max_execution_time = 300
post_max_size = 64M
upload_max_filesize = 64M
date.timezone = Asia/Jakarta
opcache.enable = 1
opcache.enable_cli = 1
"@
        $iniContent += $append
        Set-Content -Path $phpIni -Value $iniContent -Encoding UTF8
        $modified = $true
    }
    if ($modified) {
        Write-Host "✔ Modul php.ini berhasil dikonfigurasi & disinkronkan." -ForegroundColor Green
    }
}

# ------------------------------------------------------------------------------
# 5. Siapkan Direktori Storage, Database & Cache
# ------------------------------------------------------------------------------
Write-Host "[3/6] Menyiapkan direktori storage, database & cache..." -ForegroundColor Cyan
$folders = @(
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\framework\testing',
    'storage\logs',
    'storage\app\public',
    'bootstrap\cache',
    'database'
)
foreach ($f in $folders) {
    $p = Join-Path $projectRoot $f
    if (-not (Test-Path $p)) {
        New-Item -ItemType Directory -Path $p -Force | Out-Null
    }
}

# ------------------------------------------------------------------------------
# 6. Deteksi & Siapkan Composer
# ------------------------------------------------------------------------------
Write-Host "[4/6] Memeriksa package manager Composer..." -ForegroundColor Cyan
$composerPhar = Join-Path $projectRoot 'composer.phar'
$usePhar = $false

if (Test-Path $composerPhar) {
    $usePhar = $true
    Write-Host "✔ Composer portable (composer.phar) siap digunakan." -ForegroundColor Green
} else {
    $compCmd = Get-Command composer -ErrorAction SilentlyContinue
    if ($compCmd) {
        Write-Host "✔ Composer global terdeteksi di system PATH." -ForegroundColor Green
    } else {
        Write-Host "Mengunduh composer.phar resmi..." -ForegroundColor Yellow
        try {
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -sS -L --fail "https://getcomposer.org/composer-stable.phar" -o "$composerPhar" 2>$null
            } else {
                (New-Object System.Net.WebClient).DownloadFile("https://getcomposer.org/composer-stable.phar", $composerPhar)
            }
            if (Test-Path $composerPhar) {
                $usePhar = $true
                Write-Host "✔ Composer portable berhasil diunduh." -ForegroundColor Green
            }
        } catch {}
    }
}

# Auto-install vendor jika belum ada
$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
if (-not (Test-Path $vendorAutoload)) {
    Write-Host "[INFO] Menjalankan composer install untuk melengkapi dependensi vendor..." -ForegroundColor Yellow
    Write-Host "[INFO] Normal memakan waktu 1-3 menit untuk instalasi pertama kali. Mohon tunggu..." -ForegroundColor DarkGray

    if ($usePhar) {
        & $phpExe $composerPhar install --no-interaction --prefer-dist --no-scripts
        if (-not (Test-Path $vendorAutoload)) {
            & $phpExe $composerPhar install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs
        }
    } else {
        & composer install --no-interaction --prefer-dist --no-scripts
        if (-not (Test-Path $vendorAutoload)) {
            & composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs
        }
    }

    if (Test-Path $vendorAutoload) {
        Write-Host "✔ Dependensi vendor berhasil dipasang!" -ForegroundColor Green
        & $phpExe (Join-Path $projectRoot 'artisan') package:discover --ansi
    } else {
        Write-Host "⚠️ Dependensi vendor dapat dilengkapi nanti melalui Web Installer." -ForegroundColor Yellow
    }
} else {
    Write-Host "✔ Dependensi vendor sudah terpasang." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 7. Penanganan Sistem: PC Baru vs PC Yang Sudah Terinstal Sebelumnya
# ------------------------------------------------------------------------------
$lockFile = Join-Path $projectRoot 'storage\installed.lock'
$envFile = Join-Path $projectRoot '.env'
$isInstalled = (Test-Path $lockFile)

if (-not $isInstalled -and (Test-Path $envFile)) {
    $envRaw = Get-Content -Path $envFile -Raw
    if ($envRaw -match '(?m)^APP_KEY=.+' -and $envRaw -match '(?m)^DB_CONNECTION=') {
        $isInstalled = $true
    }
}

if ($isInstalled) {
    Write-Host "[INFO] Status: Sistem telah terkonfigurasi sebelumnya di PC ini." -ForegroundColor Cyan
    Write-Host "[INFO] Membersihkan cache aplikasi usang agar sinkron dengan file terbaru..." -ForegroundColor Gray
    & $phpExe (Join-Path $projectRoot 'artisan') optimize:clear 2>$null | Out-Null
    
    $pubStorage = Join-Path $projectRoot 'public\storage'
    if (-not (Test-Path $pubStorage)) {
        & $phpExe (Join-Path $projectRoot 'artisan') storage:link 2>$null | Out-Null
    }

    if (Test-Path $envFile) {
        $envRaw = Get-Content -Path $envFile -Raw
        if ($envRaw -match 'DB_CONNECTION=sqlite') {
            $sqliteDb = Join-Path $projectRoot 'database\database.sqlite'
            if (-not (Test-Path $sqliteDb)) {
                New-Item -ItemType File -Path $sqliteDb -Force | Out-Null
            }
        }
    }
} else {
    Write-Host "[INFO] Status: Instalasi baru / Belum terkonfigurasi. Membuka Web Setup Wizard..." -ForegroundColor Cyan
}

# ------------------------------------------------------------------------------
# 8. Alokasi Port 1741 & Manajemen Port Bebas Konflik
# ------------------------------------------------------------------------------
Write-Host "[5/6] Memeriksa port & koneksi jaringan..." -ForegroundColor Cyan
$port = 1741

function Test-PortInUse([int]$checkPort) {
    $tcp = Get-NetTCPConnection -LocalPort $checkPort -State Listen -ErrorAction SilentlyContinue
    return ($null -ne $tcp)
}

if (Test-PortInUse $port) {
    # Cek apakah port dipakai proses PHP sebelumnya, matikan jika ya (auto-restart bersih)
    $tcps = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    $restarted = $false
    foreach ($conn in $tcps) {
        try {
            $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
            if ($proc -and $proc.ProcessName -like "*php*") {
                Write-Host "[INFO] Menutup proses server lama di port $port (PID: $($proc.Id))..." -ForegroundColor Gray
                Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
                $restarted = $true
            }
        } catch {}
    }
    if ($restarted) { Start-Sleep -Seconds 1 }

    if (Test-PortInUse $port) {
        Write-Host "[WARN] Port 1741 digunakan oleh aplikasi lain, mencari port alternatif..." -ForegroundColor Yellow
        for ($p = 1742; $p -le 1760; $p++) {
            if (-not (Test-PortInUse $p)) {
                $port = $p
                break
            }
        }
    }
}

Write-Host "✔ Port terpilih: $port" -ForegroundColor Green

# ------------------------------------------------------------------------------
# 9. Deteksi IP Jaringan Lokal (Wi-Fi / LAN untuk Multi-Device)
# ------------------------------------------------------------------------------
$lanIp = ""
try {
    $allIps = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"
    }
    if ($allIps) {
        $lanIp = ($allIps | Select-Object -First 1).IPAddress
    }
} catch {}

# Buka firewall port
if ($isAdmin) {
    try {
        netsh advfirewall firewall add rule name="ERP POS System (Port $port)" dir=in action=allow protocol=TCP localport=$port >$null 2>&1
    } catch {}
}

# Cek status MySQL port 3306
$mysqlActive = $false
if (Test-PortInUse 3306) {
    $mysqlActive = $true
} else {
    # Coba jalankan service MySQL jika terdaftar
    try {
        Start-Service MySQL -ErrorAction SilentlyContinue
        Start-Service MySQL80 -ErrorAction SilentlyContinue
        Start-Service MariaDB -ErrorAction SilentlyContinue
        if (Test-PortInUse 3306) { $mysqlActive = $true }
    } catch {}
}

# Tentukan target URL
$targetPath = "/install.php"
if ($isInstalled) {
    if ($mysqlActive) {
        $targetPath = "/app/login"
    } elseif (Test-Path $envFile) {
        $envTxt = Get-Content -Path $envFile -Raw
        if ($envTxt -match 'DB_CONNECTION=sqlite') {
            $targetPath = "/app/login"
        }
    }
}

$localUrl = "http://localhost:$port$targetPath"
$networkUrl = if ($lanIp) { "http://$lanIp`:$port$targetPath" } else { "" }
$onlineUrl = ""

if (Test-Path $envFile) {
    $matchAppUrl = (Get-Content -Path $envFile | Where-Object { $_ -match '^APP_URL=' })
    if ($matchAppUrl) {
        $rawUrl = ($matchAppUrl -split '=', 2)[1].Trim('"'' ')
        if ($rawUrl -and $rawUrl -notmatch 'localhost|127\.0\.0\.1') {
            $onlineUrl = "$rawUrl$targetPath"
        }
    }
}

# ------------------------------------------------------------------------------
# 10. Jalankan Built-in Web Server di Background & Verifikasi Port
# ------------------------------------------------------------------------------
Write-Host "[6/6] Menyalakan Web Server di background pada port $port..." -ForegroundColor Cyan

$docRoot = Join-Path $projectRoot 'public'
$logFile = Join-Path $projectRoot 'storage\logs\server.log'

# Pastikan folder logs tersedia
$logsDir = Split-Path -Parent $logFile
if (-not (Test-Path $logsDir)) { New-Item -ItemType Directory -Path $logsDir -Force | Out-Null }

# Jalankan server secara independen di background
$serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:$port", "-t", "`"$docRoot`"" -WindowStyle Hidden -RedirectStandardOutput $logFile -RedirectStandardError $logFile -PassThru

Start-Sleep -Seconds 1

# Verifikasi apakah server berhasil mendengarkan di port
$serverReady = $false
for ($i = 0; $i -lt 5; $i++) {
    if (Test-PortInUse $port) {
        $serverReady = $true
        break
    }
    Start-Sleep -Milliseconds 500
}

if ($serverReady) {
    Write-Host "✔ Web Server berhasil berjalan di background (PID: $($serverProc.Id))." -ForegroundColor Green
} else {
    Write-Host "⚠️ Peringatan: Server belum merespon di port $port. Memeriksa log..." -ForegroundColor Yellow
    if (Test-Path $logFile) {
        Get-Content -Path $logFile -Tail 10 | ForEach-Object { Write-Host "   $_" -ForegroundColor DarkRed }
    }
}

# Buka Web Browser Otomatis
try {
    Start-Process $localUrl
} catch {}

# ------------------------------------------------------------------------------
# 11. Tampilan Ringkasan Akhir
# ------------------------------------------------------------------------------
Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "   Server ERP & POS Berhasil Aktif di Background!" -ForegroundColor Green
Write-Host "   Port Aktif : $port" -ForegroundColor White
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   - Akses dari Komputer Ini       : $localUrl" -ForegroundColor Cyan
if ($networkUrl) {
Write-Host "   - Akses dari HP / Kasir / Wi-Fi : $networkUrl" -ForegroundColor Cyan
}
if ($onlineUrl) {
Write-Host "   - Akses Internet (Online)       : $onlineUrl" -ForegroundColor Cyan
}
if ($isInstalled) {
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [TIPS] Ingin reset atau ubah konfigurasi database?" -ForegroundColor Yellow
Write-Host "          Buka Web Installer di: http://localhost:$port/install.php" -ForegroundColor White
}
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [INFO] Server tetap aktif di background meskipun jendela ini ditutup." -ForegroundColor Gray
Write-Host "   [INFO] Untuk menghentikan server kapan saja, jalankan: stop-server.bat" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
