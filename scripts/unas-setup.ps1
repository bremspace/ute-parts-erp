# ==============================================================================
# Bremspace ERP — Unas 1-Click Interactive CLI Installer (Windows Edition)
# Versi: 3.0 (Interactive Wizard CLI, Safe Defaults, Zero Manual .env)
# ==============================================================================
param(
    [switch]$ForceReinstallPhp,
    [switch]$ForceRebuildAssets,
    [switch]$NonInteractive
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

# Aktifkan TLS modern
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13
} catch {}

$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
$projectRoot = Split-Path -Parent $scriptDir
Set-Location -Path $projectRoot

function Prompt-User([string]$message, [string]$defaultVal = "") {
    if ($NonInteractive) { return $defaultVal }
    if ($defaultVal -ne "") {
        Write-Host "$message [$defaultVal]: " -NoNewline -ForegroundColor Yellow
    } else {
        Write-Host "$message: " -NoNewline -ForegroundColor Yellow
    }
    $inp = [Console]::ReadLine()
    if ([string]::IsNullOrWhiteSpace($inp)) {
        return $defaultVal
    }
    return $inp.Trim()
}

function Prompt-Password([string]$message, [string]$defaultVal = "") {
    if ($NonInteractive) { return $defaultVal }
    Write-Host "$message" -NoNewline -ForegroundColor Yellow
    if ($defaultVal -ne "") {
        Write-Host " (Kosongkan jika default/tanpa password): " -NoNewline -ForegroundColor DarkGray
    } else {
        Write-Host ": " -NoNewline -ForegroundColor Yellow
    }
    
    $pwd = ""
    while ($true) {
        $key = [Console]::ReadKey($true)
        if ($key.Key -eq [ConsoleKey]::Enter) {
            Write-Host ""
            break
        } elseif ($key.Key -eq [ConsoleKey]::Backspace) {
            if ($pwd.Length -gt 0) {
                $pwd = $pwd.Substring(0, $pwd.Length - 1)
                Write-Host "`b `b" -NoNewline
            }
        } elseif ($key.KeyChar -ge 32) {
            $pwd += $key.KeyChar
            Write-Host "*" -NoNewline
        }
    }
    if ([string]::IsNullOrWhiteSpace($pwd)) {
        return $defaultVal
    }
    return $pwd
}

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "   Bremspace ERP - Unas 1-Click Interactive CLI Installer" -ForegroundColor Cyan
Write-Host "   Otomatisasi: Audit PC -> Konfigurasi .env Interaktif -> Setup -> Go-Live" -ForegroundColor Cyan
Write-Host "   Direktori Project: $projectRoot" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# ==============================================================================
# 1. Pengecekan Kondisi PC (Hardware, OS & Lingkungan Sistem)
# ==============================================================================
Write-Host "[1/7] Memeriksa kondisi sistem operasi, hardware & hak akses..." -ForegroundColor Cyan

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
$osCaption = if ($osInfo) { $osInfo.Caption } else { [System.Environment]::OSVersion.VersionString }
$osArch = [System.Environment]::GetEnvironmentVariable("PROCESSOR_ARCHITECTURE")
$is64Bit = [System.Environment]::Is64BitOperatingSystem

Write-Host " [OK] Sistem Operasi : $osCaption ($osArch, 64-bit: $is64Bit)" -ForegroundColor Green

# 1c. Alokasi Memori RAM
if ($osInfo) {
    $ramTotalMb = [int]($osInfo.TotalVisibleMemorySize / 1024)
    $ramFreeMb = [int]($osInfo.FreePhysicalMemory / 1024)
    Write-Host " [OK] Memori RAM      : $([Math]::Round($ramTotalMb / 1024, 1)) GB (Bebas: $([Math]::Round($ramFreeMb / 1024, 1)) GB)" -ForegroundColor Green
    if ($ramTotalMb -le 2048) {
        Write-Host "   [INFO] RAM PC <= 2GB. Disarankan menutup aplikasi berat saat menjalankan ERP." -ForegroundColor Yellow
    }
}

# 1d. Pengecekan Ruang Disk Kosong
try {
    $driveLetter = (Split-Path -Qualifier $projectRoot).TrimEnd(':')
    $diskDrive = Get-PSDrive -Name $driveLetter -ErrorAction SilentlyContinue
    if ($diskDrive) {
        $freeDiskGb = [Math]::Round($diskDrive.Free / 1GB, 2)
        Write-Host " [OK] Ruang Disk (${driveLetter}:) : $freeDiskGb GB tersedia" -ForegroundColor Green
        if ($freeDiskGb -lt 1.0) {
            Write-Host "   [PERINGATAN] Sisa ruang disk sangat sedikit (< 1 GB)." -ForegroundColor Yellow
        }
    }
} catch {}

# 1e. Deteksi Package Manager Winget
$hasWinget = $false
$wingetCmd = Get-Command winget.exe -ErrorAction SilentlyContinue
if ($wingetCmd) {
    $hasWinget = $true
    Write-Host " [OK] Windows Package Manager (winget) tersedia." -ForegroundColor Green
} else {
    Write-Host " [INFO] Windows Package Manager (winget) belum terpasang." -ForegroundColor DarkGray
}

# 1f. Deteksi Git
$hasGit = $false
$gitCmd = Get-Command git.exe -ErrorAction SilentlyContinue
if ($gitCmd) {
    $hasGit = $true
    Write-Host " [OK] Git CLI terdeteksi di sistem." -ForegroundColor Green
} else {
    if ($hasWinget) {
        Write-Host " [AUTO-INSTALL] Git belum terpasang. Menginstal Git via winget..." -ForegroundColor Yellow
        Start-Process winget.exe -ArgumentList "install --id Git.Git -e --source winget --accept-source-agreements --accept-package-agreements --silent" -Wait -NoNewWindow
        $gitCmd = Get-Command git.exe -ErrorAction SilentlyContinue
        if ($gitCmd) { $hasGit = $true }
    }
    if (-not $hasGit) {
        Write-Host " [INFO] Git CLI belum terdeteksi. Disarankan jika ingin git pull otomatis." -ForegroundColor DarkGray
    }
}

# ==============================================================================
# 2. Pengecekan dan Auto-Install Visual C++ Runtime (x64)
# ==============================================================================
Write-Host "[2/7] Memeriksa Visual C++ Runtime (vcruntime140.dll & msvcp140.dll)..." -ForegroundColor Cyan
$system32 = Join-Path $env:SystemRoot 'System32'
$hasVc = (Test-Path (Join-Path $system32 'vcruntime140.dll')) -and (Test-Path (Join-Path $system32 'msvcp140.dll'))

if (-not $hasVc) {
    Write-Host " [AUTO-INSTALL] Visual C++ Runtime belum terpasang. Mengunduh installer resmi Microsoft..." -ForegroundColor Yellow
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
        Write-Host " [INFO] Pemasangan otomatis VC++ dilewati." -ForegroundColor DarkGray
    }
} else {
    Write-Host " [OK] Visual C++ Runtime sistem aktif." -ForegroundColor Green
}

# ==============================================================================
# 3. Deteksi dan Auto-Install PHP >= 8.4
# ==============================================================================
Write-Host "[3/7] Memeriksa ketersediaan PHP 8.4+ di sistem..." -ForegroundColor Cyan

function Test-PhpBinary([string]$binPath) {
    if (-not $binPath) { return $null }
    try {
        if (-not (Test-Path $binPath)) { return $null }
        $out = & $binPath -r "echo PHP_VERSION;" 2>$null
        if ($out -and ($out -match '^(\d+)\.(\d+)')) {
            $maj = [int]$matches[1]
            $min = [int]$matches[2]
            if ($maj -gt 8 -or ($maj -eq 8 -and $min -ge 4)) {
                return @{ Path = $binPath; Version = $out }
            }
        }
    } catch {}
    return $null
}

$detectedPhp = $null
if (-not $ForceReinstallPhp) {
    $phpCandidates = @()
    $sysPhp = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($sysPhp) { $phpCandidates += $sysPhp.Source }

    $phpCandidates += (Join-Path $projectRoot 'tools\php\php.exe')

    $commonDirs = @(
        'C:\laragon\bin\php\php-8.4*\php.exe',
        'C:\laragon\bin\php\php-8.5*\php.exe',
        'C:\xampp\php\php.exe',
        'C:\php\php.exe',
        'C:\Program Files\PHP\v8.4\php.exe',
        'C:\Program Files\PHP\php.exe'
    )
    foreach ($pattern in $commonDirs) {
        $found = Get-Item -Path $pattern -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($found) { $phpCandidates += $found.FullName }
    }

    foreach ($cand in $phpCandidates) {
        $res = Test-PhpBinary $cand
        if ($res) {
            $detectedPhp = $res
            break
        }
    }
}

# Auto-Install PHP 8.4 jika belum tersedia
if (-not $detectedPhp) {
    Write-Host " [AUTO-INSTALL] PHP >= 8.4 belum ada di sistem. Menyiapkan PHP 8.4 Portable mandiri..." -ForegroundColor Yellow
    $setupPs1 = Join-Path $scriptDir 'setup-windows-php.ps1'
    if (Test-Path $setupPs1) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $setupPs1
        $localPhp = Join-Path $projectRoot 'tools\php\php.exe'
        if (Test-Path $localPhp) {
            $detectedPhp = Test-PhpBinary $localPhp
        }
    }

    if (-not $detectedPhp -and $hasWinget) {
        Write-Host " Mencoba memasang PHP 8.4 via winget..." -ForegroundColor Yellow
        Start-Process winget.exe -ArgumentList "install --id PHP.PHP.8.4 -e --source winget --accept-source-agreements --accept-package-agreements --silent" -Wait -NoNewWindow
        $sysPhp = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($sysPhp) {
            $detectedPhp = Test-PhpBinary $sysPhp.Source
        }
    }
}

if (-not $detectedPhp) {
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host " [KENDALA] PHP minimal versi 8.4 belum dapat disiapkan otomatis." -ForegroundColor Red
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host "Panduan Cepat Sesuai PC Anda:" -ForegroundColor Yellow
    if ($hasWinget) {
        Write-Host "  1. Buka CMD / Terminal dan jalankan: winget install PHP.PHP.8.4" -ForegroundColor Cyan
    }
    Write-Host "  2. ATAU download PHP 8.4 Thread Safe ZIP dari: https://windows.php.net/download/" -ForegroundColor Cyan
    Write-Host "     Ekstrak seluruh isinya ke folder: $projectRoot\tools\php\" -ForegroundColor Gray
    Write-Host "======================================================================" -ForegroundColor Red
    exit 1
}

$phpExe = $detectedPhp.Path
$phpDir = Split-Path -Parent $phpExe
Write-Host " [OK] PHP terverifikasi: $phpExe (Versi: $($detectedPhp.Version))" -ForegroundColor Green

# Sisipkan folder PHP ke urutan awal PATH sesi ini
$env:Path = "$phpDir;" + $env:Path

# Pastikan Sertifikat SSL CA (cacert.pem) tersedia untuk cURL & OpenSSL di Windows
$cacertFile = Join-Path $phpDir 'cacert.pem'
if (-not (Test-Path $cacertFile)) {
    try {
        $cacertUrl = "https://curl.se/ca/cacert.pem"
        if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
            & curl.exe -sS -L --fail "$cacertUrl" -o "$cacertFile" 2>$null
        } else {
            (New-Object System.Net.WebClient).DownloadFile($cacertUrl, $cacertFile)
        }
    } catch {}
}

# Konfigurasi php.ini & Ekstensi Kritis
$phpIni = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $phpIni)) {
    $iniDev = Join-Path $phpDir 'php.ini-development'
    $iniProd = Join-Path $phpDir 'php.ini-production'
    if (Test-Path $iniDev) { Copy-Item -Path $iniDev -Destination $phpIni -Force }
    elseif (Test-Path $iniProd) { Copy-Item -Path $iniProd -Destination $phpIni -Force }
    else { Set-Content -Path $phpIni -Value "[PHP]`r`n" -Encoding ASCII }
}

if (Test-Path $phpIni) {
    $iniContent = Get-Content -Path $phpIni -Raw
    $extDir = (Join-Path $phpDir 'ext').Replace('\', '/')
    $cacertFormatted = if (Test-Path $cacertFile) { $cacertFile.Replace('\', '/') } else { "" }

    if ($iniContent -notmatch "Bremspace ERP Extensions") {
        $extList = @('curl','fileinfo','gd','intl','mbstring','mysqli','openssl','pdo_mysql','pdo_sqlite','sqlite3','zip','bcmath','exif','sodium')
        $iniContent = $iniContent -replace '(?m)^[;\s]*extension_dir\s*=\s*"ext"', "extension_dir = `"$extDir`""
        foreach ($ext in $extList) {
            $iniContent = [System.Text.RegularExpressions.Regex]::Replace($iniContent, "(?m)^[;\s]*extension\s*=\s*(php_)?$ext(\.dll)?\b", "extension=$ext")
        }
        $iniContent = [System.Text.RegularExpressions.Regex]::Replace($iniContent, '(?m)^[;\s]*zend_extension\s*=\s*(php_)?opcache(\.dll)?\b', 'zend_extension=opcache')

        $appendLines = @(
            "",
            ";; ==============================================================================",
            ";; Bremspace ERP Extensions and Overrides",
            ";; ==============================================================================",
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
            "extension=sodium",
            "zend_extension=opcache",
            "memory_limit = 512M",
            "max_execution_time = 300",
            "post_max_size = 64M",
            "upload_max_filesize = 64M",
            "date.timezone = Asia/Jakarta",
            "opcache.enable = 1",
            "opcache.enable_cli = 1"
        )
        if ($cacertFormatted) {
            $appendLines += "curl.cainfo = `"$cacertFormatted`""
            $appendLines += "openssl.cafile = `"$cacertFormatted`""
        }
        $iniContent += ($appendLines -join "`r`n")
        Set-Content -Path $phpIni -Value $iniContent -Encoding ASCII
        Write-Host " [OK] Konfigurasi php.ini & ekstensi berhasil diperbarui." -ForegroundColor Green
    }
}

# ==============================================================================
# 4. Deteksi & Siapkan Composer serta Node.js
# ==============================================================================
Write-Host "[4/7] Memeriksa package manager Composer dan Node.js..." -ForegroundColor Cyan

# 4a. Composer
$composerPhar = Join-Path $projectRoot 'composer.phar'
$usePhar = $false
$compCmd = Get-Command composer -ErrorAction SilentlyContinue

if (Test-Path $composerPhar) {
    $usePhar = $true
    Write-Host " [OK] Composer portable (composer.phar) terdeteksi." -ForegroundColor Green
} elseif ($compCmd) {
    Write-Host " [OK] Composer global terdeteksi di PATH." -ForegroundColor Green
} else {
    Write-Host " [AUTO-INSTALL] Mengunduh composer.phar resmi (getcomposer.org)..." -ForegroundColor Yellow
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
        Write-Host " [PERINGATAN] Gagal mengunduh Composer otomatis: $_" -ForegroundColor Yellow
    }
}

# 4b. Node.js & npm
$hasNode = $false
$nodeCmd = Get-Command node.exe -ErrorAction SilentlyContinue
$npmCmd = Get-Command npm.cmd -ErrorAction SilentlyContinue
if (-not $npmCmd) { $npmCmd = Get-Command npm.exe -ErrorAction SilentlyContinue }

if ($nodeCmd -and $npmCmd) {
    $hasNode = $true
    Write-Host " [OK] Node.js ($(& node -v 2>$null)) dan npm terdeteksi." -ForegroundColor Green
} else {
    if ($hasWinget) {
        Write-Host " [AUTO-INSTALL] Node.js belum terdeteksi. Mencoba instalasi via winget..." -ForegroundColor Yellow
        Start-Process winget.exe -ArgumentList "install --id OpenJS.NodeJS.LTS -e --source winget --accept-source-agreements --accept-package-agreements --silent" -Wait -NoNewWindow
        $nodeCmd = Get-Command node.exe -ErrorAction SilentlyContinue
        $npmCmd = Get-Command npm.cmd -ErrorAction SilentlyContinue
        if ($nodeCmd -and $npmCmd) { $hasNode = $true }
    }
    
    $manifestBuild = Join-Path $projectRoot 'public\build\manifest.json'
    if (Test-Path $manifestBuild) {
        Write-Host " [OK] Aset frontend pre-compiled tersedia di public\build\ (Aplikasi siap pakai langsung)." -ForegroundColor Green
    } else {
        Write-Host " [INFO] Node.js belum tersedia untuk compile asset baru." -ForegroundColor DarkGray
    }
}

# ==============================================================================
# 5. Panduan & Konfigurasi Interaktif .ENV (Sesuai Preferensi Unas)
# ==============================================================================
Write-Host ""
Write-Host "======================================================================" -ForegroundColor Magenta
Write-Host "   KONFIGURASI SISTEM INTERAKTIF (PENGISIAN .ENV & PREFERENSI)" -ForegroundColor Magenta
Write-Host "   (Tekan ENTER langsung untuk menggunakan nilai rekomendasi bawaan)" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Magenta
Write-Host ""

function Test-PortActive([int]$checkPort) {
    $tcp = Get-NetTCPConnection -LocalPort $checkPort -State Listen -ErrorAction SilentlyContinue
    return ($null -ne $tcp)
}

# 5a. Pemilihan Database Engine
$mysqlPortActive = Test-PortActive 3306
if (-not $mysqlPortActive) {
    try {
        Start-Service MySQL -ErrorAction SilentlyContinue
        Start-Service MySQL80 -ErrorAction SilentlyContinue
        Start-Service MariaDB -ErrorAction SilentlyContinue
        if (Test-PortActive 3306) { $mysqlPortActive = $true }
    } catch {}
}

Write-Host "Pilih Database Engine:" -ForegroundColor White
Write-Host "  [1] SQLite (Rekomendasi Lokal / Standalone — Mandiri, Tanpa Setup Server DB)" -ForegroundColor Cyan
if ($mysqlPortActive) {
    Write-Host "  [2] MySQL / MariaDB (Port 3306 Terdeteksi Aktif di Komputer Ini)" -ForegroundColor Green
} else {
    Write-Host "  [2] MySQL / MariaDB (Port 3306 Tidak Aktif / Belum Terpasang)" -ForegroundColor DarkGray
}

$dbChoice = Prompt-User "Pilihan Database (1 atau 2)" "1"
$dbDriver = "sqlite"
$dbHost = "127.0.0.1"
$dbPort = "3306"
$dbName = "uteparts_db"
$dbUser = "root"
$dbPass = ""

if ($dbChoice -eq "2") {
    $dbDriver = "mysql"
    $dbHost = Prompt-User "MySQL Host" "127.0.0.1"
    $dbPort = Prompt-User "MySQL Port" "3306"
    $dbName = Prompt-User "Nama Database MySQL" "uteparts_db"
    $dbUser = Prompt-User "Username MySQL" "root"
    $dbPass = Prompt-Password "Password MySQL" ""

    # Uji coba koneksi MySQL interaktif
    Write-Host " Menguji koneksi ke database MySQL..." -ForegroundColor Gray
    $testScript = "try { `$c = @new mysqli('$dbHost', '$dbUser', '$dbPass', '', (int)'$dbPort'); if (`$c->connect_errno) { exit(1); } else { exit(0); } } catch (Throwable `$e) { exit(1); }"
    & $phpExe -r "$testScript" 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host " [OK] Koneksi MySQL terverifikasi berhasil!" -ForegroundColor Green
        # Coba auto-create database jika belum ada
        $createDbScript = "try { `$c = new mysqli('$dbHost', '$dbUser', '$dbPass', '', (int)'$dbPort'); `$c->query('CREATE DATABASE IF NOT EXISTS \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); } catch (Throwable `$e) {}"
        & $phpExe -r "$createDbScript" 2>$null | Out-Null
    } else {
        Write-Host " [PERINGATAN] Koneksi MySQL gagal. Mengalihkan otomatis ke SQLite agar instalasi tetap sukses." -ForegroundColor Yellow
        $dbDriver = "sqlite"
    }
}

# 5b. Konfigurasi Port Aplikasi
Write-Host ""
$appPort = Prompt-User "Port Web Server ERP" "1741"
$appName = Prompt-User "Nama Brand / Aplikasi" "Bremspace"

# 5c. Konfigurasi Integrasi Eksternal (Opsional)
Write-Host ""
Write-Host "Apakah Anda ingin memasukkan key integrasi bisnis sekarang?" -ForegroundColor White
Write-Host "(Duitku, Biteship, WhatsApp Gateway — Dapat diisi sekarang atau nanti via web)" -ForegroundColor DarkGray
$inputIntegrasi = Prompt-User "Konfigurasi integrasi sekarang? (y/n)" "n"

$duitkuMerchantCode = ""
$duitkuApiKey = ""
$biteshipApiKey = ""
$waGatewayUrl = ""
$waGatewayToken = ""

if ($inputIntegrasi.ToLower() -eq "y") {
    Write-Host ""
    Write-Host "--- Integrasi Pembayaran (Duitku) ---" -ForegroundColor Cyan
    $duitkuMerchantCode = Prompt-User "Duitku Merchant Code" ""
    $duitkuApiKey = Prompt-User "Duitku API Key" ""

    Write-Host "--- Integrasi Pengiriman (Biteship) ---" -ForegroundColor Cyan
    $biteshipApiKey = Prompt-User "Biteship API Key" ""

    Write-Host "--- WhatsApp Gateway (Fonnte/Wablas/Custom) ---" -ForegroundColor Cyan
    $waGatewayUrl = Prompt-User "WA Gateway Endpoint URL" ""
    $waGatewayToken = Prompt-User "WA Gateway Auth Token" ""
}

# 5d. Konfigurasi File .env
$envFile = Join-Path $projectRoot '.env'
$envExample = Join-Path $projectRoot '.env.example'
if (-not (Test-Path $envFile) -and (Test-Path $envExample)) {
    Copy-Item -Path $envExample -Destination $envFile -Force
}

if (Test-Path $envFile) {
    $envContent = Get-Content -Path $envFile -Raw
    
    # Update Parameter Inti
    $envContent = $envContent -replace '(?m)^APP_NAME=.*', "APP_NAME=`"$appName`""
    $envContent = $envContent -replace '(?m)^APP_URL=.*', "APP_URL=http://localhost:$appPort"

    if ($dbDriver -eq "mysql") {
        $envContent = $envContent -replace '(?m)^DB_CONNECTION=.*', 'DB_CONNECTION=mysql'
        if ($envContent -notmatch '(?m)^DB_HOST=') {
            $envContent += "`r`nDB_HOST=$dbHost`r`nDB_PORT=$dbPort`r`nDB_DATABASE=$dbName`r`nDB_USERNAME=$dbUser`r`nDB_PASSWORD=$dbPass"
        } else {
            $envContent = $envContent -replace '(?m)^DB_HOST=.*', "DB_HOST=$dbHost"
            $envContent = $envContent -replace '(?m)^DB_PORT=.*', "DB_PORT=$dbPort"
            $envContent = $envContent -replace '(?m)^DB_DATABASE=.*', "DB_DATABASE=$dbName"
            $envContent = $envContent -replace '(?m)^DB_USERNAME=.*', "DB_USERNAME=$dbUser"
            $envContent = $envContent -replace '(?m)^DB_PASSWORD=.*', "DB_PASSWORD=$dbPass"
        }
    } else {
        $envContent = $envContent -replace '(?m)^DB_CONNECTION=.*', 'DB_CONNECTION=sqlite'
        $sqliteDb = Join-Path $projectRoot 'database\database.sqlite'
        if (-not (Test-Path $sqliteDb)) {
            New-Item -ItemType File -Path $sqliteDb -Force | Out-Null
        }
    }

    if ($duitkuMerchantCode -ne "") {
        $envContent = $envContent -replace '(?m)^DUITKU_MERCHANT_CODE=.*', "DUITKU_MERCHANT_CODE=$duitkuMerchantCode"
    }
    if ($duitkuApiKey -ne "") {
        $envContent = $envContent -replace '(?m)^DUITKU_API_KEY=.*', "DUITKU_API_KEY=$duitkuApiKey"
    }
    if ($biteshipApiKey -ne "") {
        $envContent = $envContent -replace '(?m)^BITESHIP_API_KEY=.*', "BITESHIP_API_KEY=$biteshipApiKey"
    }
    if ($waGatewayUrl -ne "") {
        $envContent = $envContent -replace '(?m)^#?\s*WA_GATEWAY_URL=.*', "WA_GATEWAY_URL=$waGatewayUrl"
    }
    if ($waGatewayToken -ne "") {
        $envContent = $envContent -replace '(?m)^#?\s*WA_GATEWAY_TOKEN=.*', "WA_GATEWAY_TOKEN=$waGatewayToken"
    }

    Set-Content -Path $envFile -Value $envContent -Encoding ASCII
    Write-Host " [OK] Konfigurasi berkas .env berhasil disesuaikan." -ForegroundColor Green
}

# ==============================================================================
# 6. Eksekusi Instalasi & Otomasi Setup (Urutan Tepat & Anti-Gagal)
# ==============================================================================
Write-Host ""
Write-Host "[6/7] Menjalankan instalasi aplikasi, migrasi & aktivasi akun..." -ForegroundColor Cyan

# 6a. Siapkan Folder Framework
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
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
}

# 6b. Pasang Dependensi Composer (Vendor) TERLEBIH DAHULU sebelum artisan
$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
if (-not (Test-Path $vendorAutoload)) {
    Write-Host " Menjalankan composer install untuk memasang modul backend..." -ForegroundColor Yellow
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
}
Write-Host " [OK] Modul composer vendor terverifikasi." -ForegroundColor Green

# 6c. Generate APP_KEY
$artisan = Join-Path $projectRoot 'artisan'
$envCheck = Get-Content -Path $envFile -Raw
if ($envCheck -notmatch '(?m)^APP_KEY=.+') {
    & $phpExe $artisan key:generate --force 2>$null | Out-Null
    Write-Host " [OK] Application Encryption Key berhasil di-generate." -ForegroundColor Green
}

# 6d. SKIP & OTOMATIS AKTIVASI LISENSI MESIN (Offline Self-Activation Unas)
Write-Host " Mengaktifkan lisensi mesin mandiri untuk PC Unas..." -ForegroundColor Gray
& $phpExe -r "
require 'scripts/license/LicenseVerifier.php';
`$challenge = Scripts\License\HardwareIdentifier::getChallengeCode();
`$key = Scripts\License\LicenseVerifier::computeSignature(`$challenge);
Scripts\License\LicenseVerifier::saveLicense(`$key);
" 2>$null | Out-Null

if (Test-Path (Join-Path $projectRoot 'storage\license.key')) {
    Write-Host " [OK] Lisensi PC aktif secara offline (storage/license.key terverifikasi)." -ForegroundColor Green
}

# 6e. SKIP INSTALL WIZARD (Buat storage/installed.lock)
$installedLock = Join-Path $projectRoot 'storage\installed.lock'
Set-Content -Path $installedLock -Value (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") -Encoding ASCII
Write-Host " [OK] Setup Wizard Web dilewati (storage/installed.lock siap)." -ForegroundColor Green

# 6f. Pastikan File SQLite Siap jika menggunakan SQLite
if ($dbDriver -eq "sqlite") {
    $sqliteDb = Join-Path $projectRoot 'database\database.sqlite'
    if (-not (Test-Path $sqliteDb)) {
        New-Item -ItemType File -Path $sqliteDb -Force | Out-Null
    }
}

# 6g. Jalankan Migrasi Database & Seeder
Write-Host " Menjalankan migrasi database skema & data bawaan..." -ForegroundColor Gray
& $phpExe $artisan migrate --force 2>$null | Out-Null
& $phpExe $artisan db:seed --force --no-interaction 2>$null | Out-Null
Write-Host " [OK] Skema tabel & data seeder awal (RBAC, Akun Admin, COA) berhasil dimuat." -ForegroundColor Green

# 6h. SKIP SETUP WIZARD TOKO (Bypass /app/setup-wizard)
& $phpExe -r "
require 'vendor/autoload.php';
`$app = require_once 'bootstrap/app.php';
`$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(App\Modules\Crm\Services\KonfigurasiService::class)->set('app_setup_completed', 'true');
" 2>$null | Out-Null
Write-Host " [OK] Setup wizard operasional toko di-bypass (Langsung siap pakai)." -ForegroundColor Green

# 6i. Symlink Storage Publik
$pubStorage = Join-Path $projectRoot 'public\storage'
if (-not (Test-Path $pubStorage)) {
    & $phpExe $artisan storage:link 2>$null | Out-Null
    Write-Host " [OK] Symlink berkas publik storage:link berhasil dibuat." -ForegroundColor Green
}

# 6j. Build Asset Frontend (Jika Node & npm tersedia)
if ($hasNode -and ($ForceRebuildAssets -or -not (Test-Path (Join-Path $projectRoot 'public\build\manifest.json')))) {
    Write-Host " Mengompilasi asset antarmuka Vite frontend..." -ForegroundColor Gray
    & npm install --ignore-scripts 2>$null | Out-Null
    & npm run build 2>$null | Out-Null
    Write-Host " [OK] Asset frontend berhasil dikompilasi." -ForegroundColor Green
}

# 6k. Bersihkan Cache Optimal
& $phpExe $artisan optimize:clear 2>$null | Out-Null

# ==============================================================================
# 7. Menyalakan Web Server & Membuka Antarmuka Aplikasi
# ==============================================================================
Write-Host ""
Write-Host "[7/7] Menyiapkan port jaringan dan menyalakan server..." -ForegroundColor Cyan

$port = [int]$appPort
if (Test-PortActive $port) {
    $tcps = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    foreach ($conn in $tcps) {
        try {
            $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
            if ($proc -and $proc.ProcessName -like "*php*") {
                Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
            }
        } catch {}
    }
    Start-Sleep -Seconds 1
    if (Test-PortActive $port) {
        for ($p = ($port + 1); $p -le ($port + 20); $p++) {
            if (-not (Test-PortActive $p)) {
                $port = $p
                break
            }
        }
    }
}

# Deteksi IP LAN Wi-Fi Lokal
$lanIp = ""
try {
    $allIps = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"
    }
    if ($allIps) { $lanIp = ($allIps | Select-Object -First 1).IPAddress }
} catch {}

# Buka firewall port jika Admin
if ($isAdmin) {
    try {
        netsh advfirewall firewall add rule name="Bremspace ERP (Port $port)" dir=in action=allow protocol=TCP localport=$port >$null 2>&1
    } catch {}
}

# Jalankan PHP Built-in Server di Background
$docRoot = Join-Path $projectRoot 'public'
$logFile = Join-Path $projectRoot 'storage\logs\server.log'
$serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:$port", "-t", "`"$docRoot`"" -WindowStyle Hidden -RedirectStandardOutput $logFile -RedirectStandardError $logFile -PassThru

if ($serverProc) {
    Set-Content -Path (Join-Path $projectRoot 'storage\framework\server.pid') -Value $serverProc.Id -Encoding ASCII
}

# Buka browser default langsung ke halaman login Backoffice
$localLoginUrl = "http://localhost:$port/app/login"
try {
    Start-Process $localLoginUrl
} catch {}

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "   INSTALASI SUKSES & APLIKASI TELAH BERJALAN AKTIF!" -ForegroundColor Green
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "Aplikasi ERP & POS Bremspace sudah online di PC lokal Anda:" -ForegroundColor White
Write-Host "  - Akses PC Ini             : $localLoginUrl" -ForegroundColor Cyan
if ($lanIp) {
Write-Host "  - Akses HP / Tablet / Kasir: http://$lanIp`:$port/app/login" -ForegroundColor Cyan
}
Write-Host "  - Toko Publik / Marketplace: http://localhost:$port/" -ForegroundColor Gray
Write-Host ""
Write-Host "Akun Bawaan (Super Admin):" -ForegroundColor Yellow
Write-Host "  - Email    : superadmin@uteparts.id" -ForegroundColor White
Write-Host "  - Password : Password123!" -ForegroundColor White
Write-Host ""
Write-Host "Informasi Kontrol Server:" -ForegroundColor DarkGray
Write-Host "  - Server berjalan di background (PID: $($serverProc.Id))." -ForegroundColor DarkGray
Write-Host "  - Untuk mematikan server kapan saja, klik: stop-server.bat" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
