# ==============================================================================
# ERP and POS System - Universal Windows Installer and Launcher
# PowerShell Engine (Pure ASCII, Rock-Solid, Zero Force-Close)
# Referensi: start-install.sh (Versi Linux)
# ==============================================================================
param(
    [switch]$ForceReinstallPhp
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

# Aktifkan TLS modern (TLS 1.2 & TLS 1.3)
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
Write-Host "   ERP & POS System - One-Click Universal Launcher (Windows)" -ForegroundColor Cyan
Write-Host "   Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)" -ForegroundColor Cyan
Write-Host "   Direktori Project: $projectRoot" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------------------------
# 1. Pengecekan Kondisi Sistem Operasi, Hardware & Hak Akses
# ------------------------------------------------------------------------------
Write-Host "[1/8] Memeriksa kondisi sistem operasi, hardware & hak akses..." -ForegroundColor Cyan

# 1a. Hak Akses Administrator
$isAdmin = $false
try {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]$identity
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
} catch {}

if ($isAdmin) {
    Write-Host " [OK] Hak Akses: Administrator (Elevated)." -ForegroundColor Green
} else {
    Write-Host " [INFO] Hak Akses: Standard User (Fitur firewall otomatis butuh Admin)." -ForegroundColor Yellow
}

# 1b. Deteksi Sistem Operasi & Arsitektur
$osInfo = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue
$csInfo = Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue

$osCaption = if ($osInfo) { $osInfo.Caption } else { [System.Environment]::OSVersion.VersionString }
$osArch = [System.Environment]::GetEnvironmentVariable("PROCESSOR_ARCHITECTURE")
$is64Bit = [System.Environment]::Is64BitOperatingSystem

Write-Host " [OK] Sistem Operasi : $osCaption ($osArch, 64-bit: $is64Bit)" -ForegroundColor Green

if (-not $is64Bit) {
    Write-Host " [PERINGATAN] Sistem Windows 32-bit terdeteksi. PHP 8.4 resmi direkomendasikan pada Windows 64-bit." -ForegroundColor Yellow
}

# 1c. Alokasi & Pengecekan Memori RAM (Minimal 2GB direkomendasikan)
if ($osInfo) {
    $ramTotalMb = [int]($osInfo.TotalVisibleMemorySize / 1024)
    $ramFreeMb = [int]($osInfo.FreePhysicalMemory / 1024)
    if ($ramTotalMb -le 2048) {
        Write-Host " [INFO] RAM terdeteksi <= 2GB (${ramTotalMb} MB, Bebas: ${ramFreeMb} MB)." -ForegroundColor Yellow
        Write-Host "   Tips: Untuk kasir multi-device bersamaan, disarankan RAM 2-4GB." -ForegroundColor Gray
    } else {
        Write-Host " [OK] Memori RAM      : $([Math]::Round($ramTotalMb / 1024, 1)) GB (Tersedia: $([Math]::Round($ramFreeMb / 1024, 1)) GB)" -ForegroundColor Green
    }
}

# 1d. Pengecekan Ruang Disk Kosong
try {
    $driveLetter = (Split-Path -Qualifier $projectRoot).TrimEnd(':')
    $diskDrive = Get-PSDrive -Name $driveLetter -ErrorAction SilentlyContinue
    if ($diskDrive) {
        $freeDiskGb = [Math]::Round($diskDrive.Free / 1GB, 2)
        Write-Host " [OK] Ruang Disk (${driveLetter}:) : $freeDiskGb GB tersedia" -ForegroundColor Green
        if ($freeDiskGb -lt 0.5) {
            Write-Host " [PERINGATAN] Ruang disk sangat sedikit (< 500 MB). Sediakan ruang disk yang cukup." -ForegroundColor Yellow
        }
    }
} catch {}

# ------------------------------------------------------------------------------
# 2. Pengecekan dan Auto-Install Visual C++ 2015-2022 Runtime (x64)
# ------------------------------------------------------------------------------
Write-Host "[2/8] Memeriksa Visual C++ Runtime (vcruntime140.dll & msvcp140.dll)..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host " [AUTO-FIX] Visual C++ Redistributable belum ada di sistem. Mengunduh installer resmi Microsoft..." -ForegroundColor Yellow
    $toolsDir = Join-Path $projectRoot 'tools'
    if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }
    $vcFile = Join-Path $toolsDir 'vc_redist.x64.exe'
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    try {
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$vcUrl" -o "$vcFile" 2>$null
        } else {
            (New-Object System.Net.WebClient).DownloadFile($vcUrl, $vcFile)
        }
        if ((Test-Path $vcFile) -and ((Get-Item $vcFile).Length -gt 1000000)) {
            Write-Host " Memasang Visual C++ Redistributable di background..." -ForegroundColor Gray
            $p = Start-Process -FilePath $vcFile -ArgumentList '/install', '/quiet', '/norestart' -PassThru -Wait
            if ($p.ExitCode -eq 0 -or $p.ExitCode -eq 3010) {
                Write-Host " [OK] Visual C++ Runtime berhasil dipasang." -ForegroundColor Green
            }
            Remove-Item -Path $vcFile -Force -ErrorAction SilentlyContinue
        }
    } catch {
        Write-Host "Catatan: Instalasi VC++ dilewati, fallback bundling DLL lokal akan digunakan jika perlu." -ForegroundColor Gray
    }
} else {
    Write-Host " [OK] Visual C++ Runtime sistem terdeteksi aktif." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 3. Deteksi dan Auto-Install PHP >= 8.4
# ------------------------------------------------------------------------------
Write-Host "[3/8] Memeriksa ketersediaan PHP 8.4+ di sistem..." -ForegroundColor Cyan

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

# Auto-install PHP 8.4 jika belum tersedia di perangkat kosongan
if (-not $detectedPhp) {
    Write-Host " PHP >= 8.4 belum terpasang. Menyiapkan auto-installer PHP untuk Windows kosongan..." -ForegroundColor Yellow

    $winAppsPath = Join-Path $env:LOCALAPPDATA 'Microsoft\WindowsApps'
    if (Test-Path $winAppsPath) {
        $env:Path = "$winAppsPath;" + $env:Path
    }

    $wingetCmd = Get-Command winget.exe -ErrorAction SilentlyContinue

    if ($wingetCmd) {
        Write-Host " [AUTO-FIX] Memasang PHP 8.4 via Windows Package Manager (winget)..." -ForegroundColor Cyan
        try {
            & winget install --id PHP.PHP.8.4 -e --silent --accept-source-agreements --accept-package-agreements
        } catch {}

        # Refresh PATH lingkungan sesi
        $machineP = [System.Environment]::GetEnvironmentVariable("Path","Machine")
        $userP = [System.Environment]::GetEnvironmentVariable("Path","User")
        $env:Path = "$machineP;$userP;$winAppsPath;" + $env:Path

        $pathPhp = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($pathPhp) {
            $detectedPhp = Test-PhpBinary $pathPhp.Source
        }

        if (-not $detectedPhp) {
            $wingetChecks = @(
                "${env:ProgramFiles}\PHP\v8.4\php.exe",
                "${env:ProgramFiles}\PHP\php.exe"
            )
            $wPkg = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($wPkg) { $wingetChecks += $wPkg.FullName }

            foreach ($wc in $wingetChecks) {
                if (Test-Path $wc) {
                    $detectedPhp = Test-PhpBinary $wc
                    if ($detectedPhp) { break }
                }
            }
        }
    }

    # Fallback: Unduh dan ekstrak paket resmi PHP 8.4 Portable ke tools\php\
    if (-not $detectedPhp) {
        Write-Host " [AUTO-FIX] Menyiapkan paket resmi PHP 8.4 Portable mandiri ke tools\php\..." -ForegroundColor Cyan
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
    Write-Host " [ERROR] PHP minimal 8.4 tidak ditemukan atau gagal disiapkan." -ForegroundColor Red
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host "Panduan Manual Cepat:" -ForegroundColor Yellow
    Write-Host "  1. Buka Terminal / CMD: winget install PHP.PHP.8.4" -ForegroundColor White
    Write-Host "  2. ATAU instal Laragon 6+ dengan PHP 8.4: https://laragon.org/download/" -ForegroundColor White
    Write-Host "  3. ATAU ekstrak zip PHP 8.4 dari https://windows.php.net/download/ ke: $projectRoot\tools\php\" -ForegroundColor White
    Write-Host "======================================================================" -ForegroundColor Red
    exit 1
}

$phpExe = $detectedPhp.Path
$phpDir = Split-Path -Parent $phpExe
Write-Host " [OK] PHP terverifikasi: $phpExe (Versi: $($detectedPhp.Version))" -ForegroundColor Green

# Sisipkan folder PHP ke urutan pertama PATH sesi ini
$env:Path = "$phpDir;" + $env:Path

# ------------------------------------------------------------------------------
# 4. Validasi Ekstensi PHP Kritis & Konfigurasi php.ini
# ------------------------------------------------------------------------------
Write-Host "[4/8] Memeriksa modul ekstensi PHP kritis (XML, SQLite, MySQL, Mbstring, Opcache, cURL)..." -ForegroundColor Cyan
$phpIni = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $phpIni)) {
    $iniDev = Join-Path $phpDir 'php.ini-development'
    $iniProd = Join-Path $phpDir 'php.ini-production'
    if (Test-Path $iniDev) {
        Copy-Item -Path $iniDev -Destination $phpIni -Force
    } elseif (Test-Path $iniProd) {
        Copy-Item -Path $iniProd -Destination $phpIni -Force
    } else {
        Set-Content -Path $phpIni -Value "[PHP]`r`n" -Encoding ASCII
    }
}

if (Test-Path $phpIni) {
    $iniContent = Get-Content -Path $phpIni -Raw
    $extDir = (Join-Path $phpDir 'ext').Replace('\', '/')
    
    if ($iniContent -notmatch "(?m)^\[ErpPos\]") {
        $appendLines = @(
            "",
            ";; ==============================================================================",
            ";; ERP and POS System Extensions and Performance",
            ";; ==============================================================================",
            "[ErpPos]",
            "extension_dir = `"$extDir`"",
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
            "memory_limit = 512M",
            "max_execution_time = 300",
            "post_max_size = 64M",
            "upload_max_filesize = 64M",
            "date.timezone = Asia/Jakarta",
            "opcache.enable = 1",
            "opcache.enable_cli = 1"
        )
        $iniContent += ($appendLines -join "`r`n")
        Set-Content -Path $phpIni -Value $iniContent -Encoding ASCII
        Write-Host " [OK] Modul php.ini berhasil dikonfigurasi & diaktifkan." -ForegroundColor Green
    }
}

# ------------------------------------------------------------------------------
# 5. Penyiapan Direktori Storage, Database & Cache
# ------------------------------------------------------------------------------
Write-Host "[5/8] Menyiapkan direktori storage, database & cache..." -ForegroundColor Cyan
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
Write-Host " [OK] Direktori sistem siap & izin tulis aktif." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 6. Deteksi & Siapkan Composer Secara Global / Portable
# ------------------------------------------------------------------------------
Write-Host "[6/8] Memeriksa package manager Composer & dependensi vendor..." -ForegroundColor Cyan
$composerPhar = Join-Path $projectRoot 'composer.phar'
$usePhar = $false

if (Test-Path $composerPhar) {
    $usePhar = $true
    Write-Host " [OK] Composer portable (composer.phar) terdeteksi." -ForegroundColor Green
} else {
    $compCmd = Get-Command composer -ErrorAction SilentlyContinue
    if ($compCmd) {
        Write-Host " [OK] Composer global terdeteksi di system PATH." -ForegroundColor Green
    } else {
        Write-Host " [AUTO-FIX] Mengunduh composer.phar resmi (getcomposer.org)..." -ForegroundColor Yellow
        try {
            if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
                & curl.exe -sS -L --fail "https://getcomposer.org/composer-stable.phar" -o "$composerPhar" 2>$null
            } else {
                (New-Object System.Net.WebClient).DownloadFile("https://getcomposer.org/composer-stable.phar", $composerPhar)
            }
            if (Test-Path $composerPhar) {
                $usePhar = $true
                Write-Host " [OK] Composer portable berhasil diunduh." -ForegroundColor Green
            }
        } catch {
            Write-Host " Gagal mengunduh Composer otomatis: $_" -ForegroundColor Yellow
        }
    }
}

# Auto-install vendor/autoload.php jika belum ada
$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
if (-not (Test-Path $vendorAutoload)) {
    Write-Host " [AUTO-FIX] Menjalankan composer install untuk melengkapi vendor dependensi..." -ForegroundColor Yellow
    Write-Host " [INFO] Proses instalasi paket sedang berlangsung, mohon tunggu..." -ForegroundColor DarkGray

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
        Write-Host " [OK] Dependensi vendor/autoload.php berhasil terpasang!" -ForegroundColor Green
        Write-Host " [AUTO-FIX] Mendaftarkan package Laravel..." -ForegroundColor Cyan
        & $phpExe (Join-Path $projectRoot 'artisan') package:discover --ansi
    } else {
        Write-Host " [WARN] Dependensi vendor belum lengkap; dapat dilengkapi melalui Web Setup Wizard." -ForegroundColor Yellow
    }
} else {
    Write-Host " [OK] Dependensi vendor aplikasi sudah terpasang (vendor/autoload.php)." -ForegroundColor Green
}

# Penanganan status: PC Baru vs PC Terkonfigurasi
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
    Write-Host " [INFO] Status: Sistem telah terkonfigurasi di komputer ini." -ForegroundColor Cyan
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
    Write-Host " [INFO] Status: Instalasi baru / Belum terkonfigurasi. Membuka Web Setup Wizard..." -ForegroundColor Cyan
}

# ------------------------------------------------------------------------------
# 7. Konfigurasi Port 1741 & Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
# ------------------------------------------------------------------------------
Write-Host "[7/8] Memeriksa port 1741, firewall & koneksi jaringan..." -ForegroundColor Cyan
$port = 1741

function Test-PortInUse([int]$checkPort) {
    $tcp = Get-NetTCPConnection -LocalPort $checkPort -State Listen -ErrorAction SilentlyContinue
    return ($null -ne $tcp)
}

if (Test-PortInUse $port) {
    # Cek apakah port dipakai proses PHP sebelumnya, hentikan untuk restart bersih
    $tcps = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    $restarted = $false
    foreach ($conn in $tcps) {
        try {
            $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
            if ($proc -and $proc.ProcessName -like "*php*") {
                Write-Host " [INFO] Menutup proses server lama di port $port (PID: $($proc.Id))..." -ForegroundColor Gray
                Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
                $restarted = $true
            }
        } catch {}
    }
    if ($restarted) { Start-Sleep -Seconds 1 }

    if (Test-PortInUse $port) {
        Write-Host " Port 1741 sedang dipakai aplikasi lain. Mencari port alternatif..." -ForegroundColor Yellow
        for ($p = 1742; $p -le 1760; $p++) {
            if (-not (Test-PortInUse $p)) {
                $port = $p
                break
            }
        }
    }
}
Write-Host " [OK] Port aktif: $port" -ForegroundColor Green

# Deteksi IP Jaringan Lokal (Wi-Fi / LAN untuk HP / Tablet / Kasir)
$lanIp = ""
try {
    $allIps = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"
    }
    if ($allIps) {
        $lanIp = ($allIps | Select-Object -First 1).IPAddress
    }
} catch {}

# Buka firewall port 1741 & 3306 jika Admin
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
    try {
        Start-Service MySQL -ErrorAction SilentlyContinue
        Start-Service MySQL80 -ErrorAction SilentlyContinue
        Start-Service MariaDB -ErrorAction SilentlyContinue
        if (Test-PortInUse 3306) { $mysqlActive = $true }
    } catch {}
}

if (-not $mysqlActive) {
    Write-Host "  [PERHATIAN] Service MySQL port 3306 belum aktif." -ForegroundColor Yellow
    Write-Host "  [TIPS] Anda dapat memilih database 'SQLite' di installer jika ingin tanpa MySQL." -ForegroundColor Gray
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
        $rawUrl = ($matchAppUrl -split '=', 2)[1].Trim('"' + "'" + ' ')
        if ($rawUrl -and $rawUrl -notmatch 'localhost|127\.0\.0\.1') {
            $onlineUrl = "$rawUrl$targetPath"
        }
    }
}

# ------------------------------------------------------------------------------
# 8. Jalankan Built-in Web Server di Background & Buka Browser
# ------------------------------------------------------------------------------
Write-Host "[8/8] Menyalakan server di background pada port $port..." -ForegroundColor Cyan

$docRoot = Join-Path $projectRoot 'public'
$logFile = Join-Path $projectRoot 'storage\logs\server.log'
$pidFile = Join-Path $projectRoot 'storage\framework\server.pid'

# Jalankan server secara independen di background
$serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:$port", "-t", "`"$docRoot`"" -WindowStyle Hidden -RedirectStandardOutput $logFile -RedirectStandardError $logFile -PassThru

# Catat PID proses server
if ($serverProc) {
    Set-Content -Path $pidFile -Value $serverProc.Id -Encoding ASCII
}

Start-Sleep -Seconds 1

# Verifikasi kesiapan server
$serverReady = $false
for ($i = 0; $i -lt 5; $i++) {
    if (Test-PortInUse $port) {
        $serverReady = $true
        break
    }
    Start-Sleep -Milliseconds 500
}

# Jalankan Queue Worker jika sistem sudah terinstall
$queuePid = ""
if ($isInstalled -and (Test-Path $envFile)) {
    $artisanPath = Join-Path $projectRoot 'artisan'
    $queueLog = Join-Path $projectRoot 'storage\logs\queue.log'
    $queuePidFile = Join-Path $projectRoot 'storage\framework\queue.pid'
    try {
        $qProc = Start-Process -FilePath $phpExe -ArgumentList "`"$artisanPath`"", "queue:work", "--sleep=3", "--tries=3" -WindowStyle Hidden -RedirectStandardOutput $queueLog -RedirectStandardError $queueLog -PassThru -ErrorAction SilentlyContinue
        if ($qProc) {
            $queuePid = $qProc.Id
            Set-Content -Path $queuePidFile -Value $qProc.Id -Encoding ASCII
        }
    } catch {}
}

# Buka Web Browser Otomatis
try {
    Start-Process $localUrl
} catch {}

# ------------------------------------------------------------------------------
# Ringkasan Akhir
# ------------------------------------------------------------------------------
Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "   Server ERP & POS Berhasil Berjalan di Background!" -ForegroundColor Green
Write-Host "   - Dari Komputer Ini       : $localUrl" -ForegroundColor Cyan
if ($networkUrl) {
Write-Host "   - Dari HP/iPhone/iPad/LAN : $networkUrl" -ForegroundColor Cyan
}
if ($onlineUrl) {
Write-Host "   - Akses Internet (Online) : $onlineUrl" -ForegroundColor Cyan
}
Write-Host "   - PID Proses Server       : $($serverProc.Id)" -ForegroundColor DarkGray
if ($queuePid) {
Write-Host "   - PID Queue Worker        : $queuePid" -ForegroundColor DarkGray
}
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [INFO] Untuk upgrade ke Full Online dengan domain sendiri, buka:" -ForegroundColor Yellow
Write-Host "          Menu Pengaturan > Sistem > Jaringan & Domain Online" -ForegroundColor White
Write-Host "   [INFO] Server tetap aktif melayani meskipun jendela ini ditutup." -ForegroundColor Gray
Write-Host "   [INFO] Untuk menghentikan server, jalankan: stop-server.bat" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
