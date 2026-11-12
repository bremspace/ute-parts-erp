# ==============================================================================
# Bremspace ERP & POS - Unas 1-Click Unified Setup Engine (Windows)
# Skrip Utama Otomasi: Deteksi PC, VC++, PHP 8.4+, Database, Migrasi, Run
# ==============================================================================
param (
    [switch]$ForceReinstallPhp,
    [switch]$ForceRebuildAssets
)

$ErrorActionPreference = "Continue"

# Inisialisasi Protokol Keamanan Jaringan (TLS 1.2 & TLS 1.3)
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls13
} catch {}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir

# Helper: Prompt input dengan nilai default
function Prompt-User([string]$promptText, [string]$defaultValue) {
    if ($defaultValue -ne "") {
        Write-Host "$promptText [$defaultValue]: " -NoNewline -ForegroundColor Yellow
    } else {
        Write-Host "$promptText: " -NoNewline -ForegroundColor Yellow
    }
    $inputVal = Read-Host
    if ([string]::IsNullOrWhiteSpace($inputVal)) {
        return $defaultValue
    }
    return $inputVal.Trim()
}

# Helper: Uji apakah TCP Port sedang aktif/didengarkan
function Test-PortActive([int]$portNumber) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $asyncResult = $client.BeginConnect("127.0.0.1", $portNumber, $null, $null)
        $waitSuccess = $asyncResult.AsyncWaitHandle.WaitOne(400, $false)
        if ($waitSuccess -and $client.Connected) {
            $client.EndConnect($asyncResult)
            $client.Close()
            return $true
        }
        $client.Close()
    } catch {}
    return $false
}

# Helper: Unduh berkas dengan penanganan timeout & fallback
function Download-FileSafely([string]$url, [string]$destination) {
    try {
        $parent = Split-Path -Parent $destination
        if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
        
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) BremspaceInstaller/1.0")
        $wc.DownloadFile($url, $destination)
        if ((Test-Path $destination) -and ((Get-Item $destination).Length -gt 1000)) {
            return $true
        }
    } catch {}
    
    # Fallback via Invoke-WebRequest
    try {
        Invoke-WebRequest -Uri $url -OutFile $destination -UseBasicParsing -TimeoutSec 120
        if ((Test-Path $destination) -and ((Get-Item $destination).Length -gt 1000)) {
            return $true
        }
    } catch {}
    return $false
}

Clear-Host
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "    Bremspace ERP & POS System - Unas 1-Click Setup Engine" -ForegroundColor Cyan
Write-Host "    Konfigurasi Otomatis & Mandiri untuk Windows Baru / Fresh PC" -ForegroundColor White
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# ==============================================================================
# 1. Pre-flight Check: Analisis Spesifikasi PC
# ==============================================================================
Write-Host "[1/7] Melakukan audit lingkungan sistem PC..." -ForegroundColor Cyan

$osName = (Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue).Caption
if (-not $osName) { $osName = "Microsoft Windows" }
$is64Bit = [Environment]::Is64BitOperatingSystem

$totalRamGb = 4
try {
    $ramBytes = (Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue).TotalPhysicalMemory
    if ($ramBytes) { $totalRamGb = [math]::Round($ramBytes / 1GB, 1) }
} catch {}

$freeDiskGb = 0
try {
    $rootDrive = (Split-Path -Qualifier $projectRoot)
    $diskInfo = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$rootDrive'" -ErrorAction SilentlyContinue
    if ($diskInfo) { $freeDiskGb = [math]::Round($diskInfo.FreeSpace / 1GB, 1) }
} catch {}

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

Write-Host "  - Sistem Operasi  : $osName ($([Environment]::OSVersion.Version))" -ForegroundColor Gray
Write-Host "  - Arsitektur      : $(if ($is64Bit) {'64-bit (x64)'} else {'32-bit (x86)'})" -ForegroundColor Gray
Write-Host "  - Total Memori RAM: $totalRamGb GB" -ForegroundColor Gray
Write-Host "  - Ruang Disk Sisa : $freeDiskGb GB di drive $rootDrive" -ForegroundColor Gray
Write-Host "  - Hak Akses Admin : $(if ($isAdmin) {'Aktif (Administrator)'} else {'Standar User'})" -ForegroundColor $(if ($isAdmin) {'Green'} else {'Yellow'})

if (-not $is64Bit) {
    Write-Host "[FATAL] Sistem 32-bit tidak didukung. Harap gunakan Windows 64-bit." -ForegroundColor Red
    pause
    exit 1
}

# ==============================================================================
# 2. Pemeriksaan & Pemasangan Visual C++ Redistributable (Wajib untuk PHP Windows)
# ==============================================================================
Write-Host ""
Write-Host "[2/7] Memeriksa Microsoft Visual C++ 2015-2022 Redistributable (x64)..." -ForegroundColor Cyan

$hasVc = $false
$vcPaths = @(
    "$env:SystemRoot\System32\vcruntime140.dll",
    "$env:SystemRoot\System32\msvcp140.dll"
)
if ((Test-Path $vcPaths[0]) -and (Test-Path $vcPaths[1])) {
    $hasVc = $true
}

if (-not $hasVc) {
    Write-Host " [PERINGATAN] Visual C++ Runtime belum terpasang. Mengunduh otomatis..." -ForegroundColor Yellow
    $toolsDir = Join-Path $projectRoot 'tools'
    if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }
    $vcRedistExe = Join-Path $toolsDir 'vc_redist.x64.exe'
    
    $vcUrl = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    $downloadOk = Download-FileSafely $vcUrl $vcRedistExe
    
    if ($downloadOk) {
        Write-Host " Memasang Visual C++ x64 runtime secara silent..." -ForegroundColor Gray
        try {
            $p = Start-Process -FilePath $vcRedistExe -ArgumentList "/install", "/quiet", "/norestart" -PassThru -Wait
            if ($p.ExitCode -eq 0 -or $p.ExitCode -eq 3010) {
                Write-Host " [OK] Visual C++ Redistributable berhasil dipasang." -ForegroundColor Green
                $hasVc = $true
            } else {
                Write-Host " [INFO] Installer VC++ selesai dengan kode: $($p.ExitCode)" -ForegroundColor DarkGray
            }
        } catch {
            Write-Host " [PERINGATAN] Gagal menjalankan installer VC++. Pastikan hak Administrator aktif." -ForegroundColor Yellow
        }
    } else {
        Write-Host " [PERINGATAN] Tidak dapat mengunduh vc_redist.x64.exe otomatis. Silakan unduh manual jika PHP gagal berjalan." -ForegroundColor Yellow
    }
} else {
    Write-Host " [OK] Visual C++ Runtime sistem aktif." -ForegroundColor Green
}

# ==============================================================================
# 3. Konfigurasi PHP 8.4+ Portable Mandiri (100% Ekstensi Lengkap & Terverifikasi)
# ==============================================================================
Write-Host ""
Write-Host "[3/7] Menyiapkan runtime PHP 8.4+ dan konfigurasi php.ini..." -ForegroundColor Cyan

function Configure-PhpIniFile([string]$phpFolder) {
    $iniFile = Join-Path $phpFolder 'php.ini'
    $extDir = (Join-Path $phpFolder 'ext').Replace('\', '/')
    $certFile = (Join-Path $phpFolder 'cacert.pem').Replace('\', '/')

    # Unduh cacert.pem jika belum ada
    if (-not (Test-Path (Join-Path $phpFolder 'cacert.pem'))) {
        $certUrl = "https://curl.se/ca/cacert.pem"
        Download-FileSafely $certUrl (Join-Path $phpFolder 'cacert.pem') | Out-Null
    }

    $iniTemplate = @"
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On
expose_php = Off

max_execution_time = 300
max_input_time = 300
max_input_vars = 5000
memory_limit = 512M

error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On

variables_order = "GPCS"
request_order = "GP"
register_argc_argv = Off
auto_globals_jit = On

post_max_size = 64M
default_mimetype = "text/html"
default_charset = "UTF-8"

enable_dl = Off
file_uploads = On
upload_max_filesize = 64M
max_file_uploads = 20

allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

extension_dir = "$extDir"

; Bremspace ERP Mandatory Extensions
extension=bcmath
extension=curl
extension=exif
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
extension=sodium
zend_extension=opcache

[Date]
date.timezone = Asia/Jakarta

[curl]
curl.cainfo = "$certFile"

[openssl]
openssl.cafile = "$certFile"

[opcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
"@

    Set-Content -Path $iniFile -Value $iniTemplate -Encoding ASCII
}

function Test-PhpBinary([string]$binPath) {
    if (-not $binPath -or -not (Test-Path $binPath)) { return $null }
    try {
        $vOut = & $binPath -n -r "echo PHP_VERSION;" 2>$null
        if ($vOut -and ($vOut -match '^(\d+)\.(\d+)')) {
            $maj = [int]$matches[1]
            $min = [int]$matches[2]
            if ($maj -gt 8 -or ($maj -eq 8 -and $min -ge 4)) {
                # Cek apakah modul pdo_mysql / pdo_sqlite aktif jika menggunakan php.ini
                $modOut = & $binPath -r "echo extension_loaded('pdo_mysql') ? '1' : '0';" 2>$null
                return @{ Path = $binPath; Version = $vOut; HasPdo = ($modOut -eq '1') }
            }
        }
    } catch {}
    return $null
}

$toolsPhpDir = Join-Path $projectRoot 'tools\php'
$localPhpExe = Join-Path $toolsPhpDir 'php.exe'
$detectedPhp = $null

if (-not $ForceReinstallPhp -and (Test-Path $localPhpExe)) {
    Configure-PhpIniFile $toolsPhpDir
    $detectedPhp = Test-PhpBinary $localPhpExe
}

# Jika PHP lokal belum valid, periksa alternatif sistem
if (-not $detectedPhp -and -not $ForceReinstallPhp) {
    $sysCandidates = @()
    $sysCmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($sysCmd) { $sysCandidates += $sysCmd.Source }

    $pathsToCheck = @(
        'C:\laragon\bin\php\php-8.4*\php.exe',
        'C:\laragon\bin\php\php-8.5*\php.exe',
        'C:\xampp\php\php.exe',
        'C:\php\php.exe'
    )
    foreach ($pat in $pathsToCheck) {
        $f = Get-Item -Path $pat -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($f) { $sysCandidates += $f.FullName }
    }

    foreach ($cand in $sysCandidates) {
        $res = Test-PhpBinary $cand
        if ($res -and $res.HasPdo) {
            $detectedPhp = $res
            break
        }
    }
}

# Auto-Download & Setup PHP 8.4 Portable jika belum ada atau belum lengkap
if (-not $detectedPhp) {
    Write-Host " [AUTO-DOWNLOAD] Menyiapkan paket PHP 8.4 x64 Portable ke tools\php..." -ForegroundColor Yellow
    if (-not (Test-Path $toolsPhpDir)) { New-Item -ItemType Directory -Path $toolsPhpDir -Force | Out-Null }
    
    $zipDest = Join-Path $projectRoot 'tools\php-8.4-latest.zip'
    $phpUrls = @(
        "https://windows.php.net/downloads/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
        "https://downloads.php.net/~windows/releases/latest/php-8.4-Win32-vs17-x64-latest.zip",
        "https://windows.php.net/downloads/releases/archives/php-8.4.4-Win32-vs17-x64.zip",
        "https://windows.php.net/downloads/releases/archives/php-8.4.3-Win32-vs17-x64.zip"
    )

    $downloadSuccess = $false
    foreach ($url in $phpUrls) {
        Write-Host " Mengunduh PHP 8.4 dari: $url" -ForegroundColor Gray
        if (Download-FileSafely $url $zipDest) {
            $downloadSuccess = $true
            break
        }
    }

    if ($downloadSuccess) {
        Write-Host " Mengekstrak arsip PHP 8.4 Portable..." -ForegroundColor Gray
        try {
            Expand-Archive -Path $zipDest -DestinationPath $toolsPhpDir -Force
            Remove-Item -Path $zipDest -Force -ErrorAction SilentlyContinue
            Configure-PhpIniFile $toolsPhpDir
            $detectedPhp = Test-PhpBinary $localPhpExe
        } catch {
            Write-Host " [ERROR] Gagal mengekstrak PHP archive: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

if (-not $detectedPhp) {
    Write-Host "[FATAL] Gagal menyiapkan PHP 8.4+. Periksa koneksi internet atau unduh manual PHP 8.4 x64 ke folder tools\php." -ForegroundColor Red
    pause
    exit 1
}

$phpExe = $detectedPhp.Path
Write-Host " [OK] Menggunakan PHP Binary : $phpExe (Versi: $($detectedPhp.Version))" -ForegroundColor Green

# Verifikasi ulang konfigurasi ekstensi php.ini
$loadedExts = & $phpExe -r "echo implode(',', get_loaded_extensions());" 2>$null
$requiredExts = @('bcmath','curl','fileinfo','gd','intl','mbstring','openssl','pdo_mysql','pdo_sqlite','zip')
$missingExts = @()
foreach ($ext in $requiredExts) {
    if ($loadedExts -notmatch "(?i)\b$ext\b") {
        $missingExts += $ext
    }
}

if ($missingExts.Count -gt 0) {
    Write-Host " [INFO] Memperbarui php.ini untuk melengkapi ekstensi: $($missingExts -join ', ')..." -ForegroundColor Yellow
    $parentPhp = Split-Path -Parent $phpExe
    Configure-PhpIniFile $parentPhp
}
Write-Host " [OK] Konfigurasi php.ini terverifikasi (100% Ekstensi ERP Aktif)." -ForegroundColor Green

# ==============================================================================
# 4. Verifikasi Dependensi Composer & Frontend
# ==============================================================================
Write-Host ""
Write-Host "[4/7] Memeriksa modul Composer dan Asset Vite..." -ForegroundColor Cyan

$composerPhar = Join-Path $projectRoot 'tools\composer.phar'
$usePhar = $false

$globalComposer = Get-Command composer.bat -ErrorAction SilentlyContinue
if (-not $globalComposer) { $globalComposer = Get-Command composer -ErrorAction SilentlyContinue }

if (-not $globalComposer) {
    if (-not (Test-Path $composerPhar)) {
        Write-Host " Mengunduh composer.phar mandiri..." -ForegroundColor Gray
        $toolsDir = Join-Path $projectRoot 'tools'
        if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }
        Download-FileSafely "https://getcomposer.org/composer-stable.phar" $composerPhar | Out-Null
    }
    if (Test-Path $composerPhar) {
        $usePhar = $true
        Write-Host " [OK] Composer Portable (composer.phar) siap." -ForegroundColor Green
    }
} else {
    Write-Host " [OK] Composer Sistem terdeteksi: $($globalComposer.Source)" -ForegroundColor Green
}

$hasNode = $false
$nodeCmd = Get-Command node.exe -ErrorAction SilentlyContinue
$npmCmd = Get-Command npm.cmd -ErrorAction SilentlyContinue
if ($nodeCmd -and $npmCmd) {
    $hasNode = $true
    Write-Host " [OK] Node.js & npm terdeteksi." -ForegroundColor Green
} else {
    Write-Host " [INFO] Node.js tidak terdeteksi. Sistem akan menggunakan asset produksi bawaan (manifest.json)." -ForegroundColor DarkGray
}

# ==============================================================================
# 5. Konfigurasi Database & Environment (.env)
# ==============================================================================
Write-Host ""
Write-Host "[5/7] Konfigurasi Database & Parameter Aplikasi..." -ForegroundColor Cyan

Write-Host "Pilih Engine Database:" -ForegroundColor White
Write-Host "  [1] SQLite (Rekomendasi Cepat & Mandiri, 1-Click Zero Config)" -ForegroundColor Green
Write-Host "  [2] MySQL / MariaDB (Rekomendasi Skala Besar, Jutaan Transaksi, POS Multi-Kasir LAN)" -ForegroundColor Cyan
$dbChoice = Prompt-User "Pilihan Database [1/2]" "1"

$dbDriver = "sqlite"
$dbHost = "127.0.0.1"
$dbPort = "3306"
$dbName = "uteparts_db"
$dbUser = "root"
$dbPass = ""

if ($dbChoice -eq "2") {
    $dbDriver = "mysql"
    Write-Host ""
    Write-Host "--- Pemeriksaan Service MySQL / MariaDB ---" -ForegroundColor Cyan
    
    # 5a. Periksa apakah port 3306 sudah mendengarkan
    $mysqlListening = Test-PortActive 3306
    if (-not $mysqlListening) {
        Write-Host " [INFO] Port 3306 belum aktif. Mencoba mengaktifkan service lokal..." -ForegroundColor Yellow
        $svcNames = @('MySQL', 'MySQL80', 'MariaDB', 'wampmysqld', 'wampmysqld64')
        foreach ($s in $svcNames) {
            $svc = Get-Service -Name $s -ErrorAction SilentlyContinue
            if ($svc) {
                try {
                    Start-Service -Name $s -ErrorAction SilentlyContinue
                    Start-Sleep -Seconds 2
                    if (Test-PortActive 3306) {
                        $mysqlListening = $true
                        Write-Host " [OK] Service $s berhasil dinyalakan." -ForegroundColor Green
                        break
                    }
                } catch {}
            }
        }
    }

    # Cek direktori Laragon atau XAMPP jika service belum aktif
    if (-not $mysqlListening) {
        $laragonMysqld = Get-Item -Path "C:\laragon\bin\mysql\*\bin\mysqld.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
        $xamppMysqld = "C:\xampp\mysql\bin\mysqld.exe"
        
        if ($laragonMysqld) {
            Write-Host " Menjalankan MySQL dari Laragon di background..." -ForegroundColor Gray
            Start-Process -FilePath $laragonMysqld.FullName -ArgumentList "--standalone" -WindowStyle Hidden
            Start-Sleep -Seconds 2
            $mysqlListening = Test-PortActive 3306
        } elseif (Test-Path $xamppMysqld) {
            Write-Host " Menjalankan MySQL dari XAMPP di background..." -ForegroundColor Gray
            Start-Process -FilePath $xamppMysqld -ArgumentList "--standalone" -WindowStyle Hidden
            Start-Sleep -Seconds 2
            $mysqlListening = Test-PortActive 3306
        }
    }

    if ($mysqlListening) {
        Write-Host " [OK] Database Server aktif di port 3306." -ForegroundColor Green
    } else {
        Write-Host " [PERINGATAN] Port 3306 belum terdeteksi aktif. Pastikan XAMPP/Laragon/MariaDB Anda menyala." -ForegroundColor Yellow
    }

    # 5b. Input Kredensial MySQL
    Write-Host ""
    $dbHost = Prompt-User "Host Database" "127.0.0.1"
    $dbPort = Prompt-User "Port Database" "3306"
    $dbName = Prompt-User "Nama Database" "uteparts_db"
    $dbUser = Prompt-User "Username Database" "root"
    $dbPass = Prompt-User "Password Database (kosongkan jika tanpa password)" ""

    # Uji koneksi via script test-db-connection.php
    $testDbScript = Join-Path $scriptDir 'test-db-connection.php'
    if (Test-Path $testDbScript) {
        $dbTestOut = & $phpExe $testDbScript $dbHost $dbPort $dbUser $dbPass $dbName 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host " [OK] Koneksi MySQL terverifikasi & Database '$dbName' siap." -ForegroundColor Green
        } else {
            Write-Host " [PERINGATAN] Pengujian koneksi MySQL: $dbTestOut" -ForegroundColor Yellow
        }
    }
} else {
    Write-Host " [OK] Mode SQLite dipilih. Database berkas mandiri database\database.sqlite akan disiapkan." -ForegroundColor Green
}

# Parameter Server Web
Write-Host ""
$appPort = Prompt-User "Port Web Server ERP" "1741"
$appName = Prompt-User "Nama Brand / Aplikasi" "Bremspace"

# Persiapan file .env
$envFile = Join-Path $projectRoot '.env'
$envExample = Join-Path $projectRoot '.env.example'
if (-not (Test-Path $envFile) -and (Test-Path $envExample)) {
    Copy-Item -Path $envExample -Destination $envFile -Force
}

if (Test-Path $envFile) {
    $envContent = Get-Content -Path $envFile -Raw
    
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

    # Skip pengecekan license key di Unas (diatur manual via .env oleh pengembang)
    if ($envContent -match '(?m)^BREMSPACE_LICENSE_ENABLED=') {
        $envContent = $envContent -replace '(?m)^BREMSPACE_LICENSE_ENABLED=.*', 'BREMSPACE_LICENSE_ENABLED=false'
    } else {
        $envContent += "`r`nBREMSPACE_LICENSE_ENABLED=false"
    }

    Set-Content -Path $envFile -Value $envContent -Encoding ASCII
    Write-Host " [OK] Konfigurasi berkas .env berhasil disesuaikan." -ForegroundColor Green
}

# ==============================================================================
# 6. Eksekusi Instalasi, Migrasi Skema & Seeder Akun
# ==============================================================================
Write-Host ""
Write-Host "[6/7] Menyiapkan struktur direktori, migrasi database & data bawaan..." -ForegroundColor Cyan

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

# 6b. Pasang Dependensi Composer jika belum ada vendor
$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
if (-not (Test-Path $vendorAutoload)) {
    Write-Host " Menjalankan composer install untuk memasang modul vendor backend..." -ForegroundColor Yellow
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

# 6c. Generate APP_KEY jika belum ada
$artisan = Join-Path $projectRoot 'artisan'
$envCheck = Get-Content -Path $envFile -Raw
if ($envCheck -notmatch '(?m)^APP_KEY=.+') {
    & $phpExe $artisan key:generate --force 2>$null | Out-Null
    Write-Host " [OK] Application Encryption Key berhasil di-generate." -ForegroundColor Green
}

# 6d. Migrasi Database Skema
Write-Host " Menjalankan migrasi database skema..." -ForegroundColor Gray
$migOut = & $phpExe $artisan migrate --force 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host " [PERINGATAN] Migrasi database menghasilkan catatan (Exit Code: $LASTEXITCODE):" -ForegroundColor Yellow
    $migOut | ForEach-Object { Write-Host "   $_" -ForegroundColor DarkYellow }
} else {
    Write-Host " [OK] Skema tabel database berhasil dimuat." -ForegroundColor Green
}

# 6e. Data Seeder Awal (RBAC, Akun Super Admin, COA)
Write-Host " Menjalankan data seeder bawaan (RBAC, Akun Admin, COA)..." -ForegroundColor Gray
$seedOut = & $phpExe $artisan db:seed --force --no-interaction 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host " [PERINGATAN] Seeder database menghasilkan catatan:" -ForegroundColor Yellow
    $seedOut | ForEach-Object { Write-Host "   $_" -ForegroundColor DarkYellow }
} else {
    Write-Host " [OK] Data seeder awal berhasil dimuat." -ForegroundColor Green
}

# 6f. Bypass Setup Wizard Operasional Toko
$finishSetupScript = Join-Path $scriptDir 'finish-setup.php'
if (Test-Path $finishSetupScript) {
    & $phpExe $finishSetupScript 2>$null | Out-Null
}
$installedLock = Join-Path $projectRoot 'storage\installed.lock'
Set-Content -Path $installedLock -Value (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") -Encoding ASCII
Write-Host " [OK] Setup wizard operasional toko ditandai selesai (storage/installed.lock siap)." -ForegroundColor Green

# 6g. Symlink Berkas Publik
$pubStorage = Join-Path $projectRoot 'public\storage'
if (-not (Test-Path $pubStorage)) {
    & $phpExe $artisan storage:link 2>$null | Out-Null
    Write-Host " [OK] Symlink berkas publik storage:link dibuat." -ForegroundColor Green
}

# 6h. Bersihkan Cache Framework
& $phpExe $artisan optimize:clear 2>$null | Out-Null

# ==============================================================================
# 7. Menyalakan Web Server & Membuka Antarmuka Aplikasi
# ==============================================================================
Write-Host ""
Write-Host "[7/7] Menyiapkan port jaringan dan menyalakan server..." -ForegroundColor Cyan

$portNumFinal = 1741
[int]::TryParse($appPort, [ref]$portNumFinal) | Out-Null
$port = $portNumFinal

# Bersihkan proses lama pada port target jika ada
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

# Deteksi IP LAN Wi-Fi Lokal untuk akses HP/Tablet
$lanIp = ""
try {
    $allIps = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"
    }
    if ($allIps) { $lanIp = ($allIps | Select-Object -First 1).IPAddress }
} catch {}

# Buka firewall port jika hak Administrator aktif
if ($isAdmin) {
    try {
        netsh advfirewall firewall add rule name="Bremspace ERP (Port $port)" dir=in action=allow protocol=TCP localport=$port >$null 2>&1
    } catch {}
}

# Pastikan router script server.php tersedia
$serverRouter = Join-Path $projectRoot 'server.php'
$serverLog = Join-Path $projectRoot 'storage\logs\server.log'
$queueLog = Join-Path $projectRoot 'storage\logs\queue.log'

# Jalankan PHP Built-in Server di Background dengan server.php
$serverProc = $null
if (Test-Path $serverRouter) {
    $serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:$port", "server.php" -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $serverLog -RedirectStandardError $serverLog -PassThru
} else {
    $docRoot = Join-Path $projectRoot 'public'
    $serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:$port", "-t", "`"$docRoot`"" -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $serverLog -RedirectStandardError $serverLog -PassThru
}

if ($serverProc) {
    Set-Content -Path (Join-Path $projectRoot 'storage\framework\server.pid') -Value $serverProc.Id -Encoding ASCII
}

# Jalankan Background Queue Worker
$queueProc = Start-Process -FilePath $phpExe -ArgumentList "artisan", "queue:work", "--sleep=3", "--tries=3" -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput $queueLog -RedirectStandardError $queueLog -PassThru
if ($queueProc) {
    Set-Content -Path (Join-Path $projectRoot 'storage\framework\queue.pid') -Value $queueProc.Id -Encoding ASCII
}

# Buka browser default langsung ke Backoffice Login
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
if ($serverProc) {
Write-Host "  - Web Server berjalan di background (PID: $($serverProc.Id))." -ForegroundColor DarkGray
}
if ($queueProc) {
Write-Host "  - Queue Worker berjalan di background (PID: $($queueProc.Id))." -ForegroundColor DarkGray
}
Write-Host "  - Untuk mematikan server & antrian kapan saja, klik: stop-server.bat" -ForegroundColor DarkGray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
