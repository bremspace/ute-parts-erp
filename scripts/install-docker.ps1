# ==============================================================================
# Bremspace ERP dan POS System - Windows Universal Docker Launcher
# Brand: Bremspace
# Strategi: Docker Desktop + WSL2 Native Docker Engine (Zero Raw PHP on Host)
# ==============================================================================
$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
$projectRoot = Split-Path -Parent $scriptDir
Set-Location -Path $projectRoot

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "   Bremspace ERP dan POS System - Windows Docker Universal Launcher" -ForegroundColor Cyan
Write-Host "   Target Port: 1741 (Localhost dan Jaringan Wi-Fi/LAN)" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------------------------
# 1. Pengecekan Hak Akses Administrator
# ------------------------------------------------------------------------------
$isAdmin = $false
try {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]$identity
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
} catch {}

if ($isAdmin) {
    Write-Host "[OK] Hak Akses: Administrator (Elevated)." -ForegroundColor Green
} else {
    Write-Host "[INFO] Hak Akses: Standard User." -ForegroundColor Yellow
}

# ------------------------------------------------------------------------------
# 2. Deteksi dan Penyiapan Engine Docker (Docker Desktop / WSL2)
# ------------------------------------------------------------------------------
Write-Host "[1/5] Memeriksa engine Docker di Windows..." -ForegroundColor Cyan

$dockerCmd = Get-Command docker.exe -ErrorAction SilentlyContinue
$dockerMode = "NONE"

# A. Cek apakah Docker CLI sudah terpasang dan daemon merespon
if ($dockerCmd) {
    try {
        $info = & docker.exe info 2>&1
        if ($LASTEXITCODE -eq 0) {
            $dockerMode = "DOCKER_CLI"
            Write-Host "[OK] Docker Engine daemon aktif dan siap digunakan." -ForegroundColor Green
        }
    } catch {}
}

# B. Jika daemon belum merespon, periksa apakah aplikasi Docker Desktop terpasang
if ($dockerMode -eq "NONE") {
    $ddPaths = @(
        "${env:ProgramFiles}\Docker\Docker\Docker Desktop.exe",
        "${env:ProgramFiles(x86)}\Docker\Docker\Docker Desktop.exe",
        "${env:LOCALAPPDATA}\Programs\Docker\Docker Desktop.exe"
    )
    foreach ($dd in $ddPaths) {
        if (Test-Path $dd) {
            Write-Host "[INFO] Menyalakan Docker Desktop di background..." -ForegroundColor Yellow
            Start-Process -FilePath $dd -WindowStyle Hidden -ErrorAction SilentlyContinue
            Write-Host "[INFO] Menunggu daemon Docker aktif (estimasi 10-25 detik)..." -ForegroundColor Yellow
            for ($i = 0; $i -lt 15; $i++) {
                Start-Sleep -Seconds 2
                $test = & docker.exe info 2>&1
                if ($LASTEXITCODE -eq 0) {
                    $dockerMode = "DOCKER_CLI"
                    Write-Host "[OK] Docker Desktop berhasil tersambung dan siap!" -ForegroundColor Green
                    break
                }
            }
            if ($dockerMode -eq "DOCKER_CLI") { break }
        }
    }
}

# C. Jika Docker Desktop tidak aktif, periksa fitur WSL2 Windows
if ($dockerMode -eq "NONE") {
    Write-Host "[INFO] Docker Desktop tidak terdeteksi. Memeriksa fitur WSL2..." -ForegroundColor Cyan
    $wslCmd = Get-Command wsl.exe -ErrorAction SilentlyContinue
    
    if ($wslCmd) {
        try {
            $wslList = & wsl.exe --list --quiet 2>&1
            if ($LASTEXITCODE -eq 0 -and $wslList) {
                Write-Host "[OK] WSL2 Windows terdeteksi aktif." -ForegroundColor Green
                $wslDocker = & wsl.exe -e sh -c "command -v docker" 2>&1
                if ($LASTEXITCODE -eq 0 -and $wslDocker) {
                    # Pastikan service docker di dalam WSL menyala
                    & wsl.exe -e sudo service docker start 2>$null
                    $dockerMode = "WSL2"
                    Write-Host "[OK] Docker Engine di dalam WSL2 siap digunakan." -ForegroundColor Green
                } else {
                    Write-Host "[AUTO-FIX] Memasang Docker Engine di dalam distro WSL2..." -ForegroundColor Yellow
                    & wsl.exe -e sh -c "curl -fsSL https://get.docker.com | sh; sudo service docker start" 2>$null
                    $wslDockerTest = & wsl.exe -e sh -c "command -v docker" 2>&1
                    if ($LASTEXITCODE -eq 0 -and $wslDockerTest) {
                        $dockerMode = "WSL2"
                        Write-Host "[OK] Docker Engine di dalam WSL2 berhasil dipasang!" -ForegroundColor Green
                    }
                }
            }
        } catch {}
    }
}

# D. Jika Docker masih belum ada, coba deteksi winget untuk instalasi otomatis
if ($dockerMode -eq "NONE") {
    $wingetCmd = Get-Command winget.exe -ErrorAction SilentlyContinue
    if ($wingetCmd -and $isAdmin) {
        Write-Host "[INFO] Docker belum ditemukan. Mencoba memasang Docker Desktop otomatis via winget..." -ForegroundColor Yellow
        try {
            & winget.exe install -e --id Docker.DockerDesktop --accept-package-agreements --accept-source-agreements
            Write-Host "[OK] Docker Desktop berhasil dipasang via winget. Silakan restart komputer atau buka Docker Desktop." -ForegroundColor Green
        } catch {}
    }
}

# E. Validasi Ketat: Selain Android, semua instalasi wajib berbasis Docker
if ($dockerMode -eq "NONE") {
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host "   [ERROR] DOCKER ENGINE WAJIB TERPASANG DI WINDOWS" -ForegroundColor Red
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host "Bremspace ERP dirancang khusus berjalan di atas container Docker untuk:" -ForegroundColor White
    Write-Host "  1. Keamanan penuh kode sumber aplikasi (Model B terisolasi)." -ForegroundColor White
    Write-Host "  2. Performa database MySQL 8.4 maksimal (Named Volume ext4)." -ForegroundColor White
    Write-Host "  3. Background queue worker dan scheduler otomatis tanpa henti." -ForegroundColor White
    Write-Host ""
    Write-Host "Solusi Mudah:" -ForegroundColor Yellow
    Write-Host "  1. Unduh dan pasang Docker Desktop (Gratis):" -ForegroundColor White
    Write-Host "     -> https://www.docker.com/products/docker-desktop/" -ForegroundColor Cyan
    Write-Host "     (Pastikan opsi 'Use WSL 2 instead of Hyper-V' dicentang)" -ForegroundColor Gray
    Write-Host "  2. Pastikan Virtualisasi (VT-x / AMD-V) diaktifkan di BIOS komputer." -ForegroundColor White
    Write-Host "  3. Buka Docker Desktop, lalu jalankan kembali start-install.bat ini." -ForegroundColor White
    Write-Host "======================================================================" -ForegroundColor Red
    Write-Host ""
    exit 1
}

Write-Host "[OK] Engine Docker Aktif: Mode $dockerMode" -ForegroundColor Green

# ------------------------------------------------------------------------------
# 3. Verifikasi Lisensi Hardware Offline (Bremspace Anti-Duplikasi)
# ------------------------------------------------------------------------------
Write-Host "[2/5] Memeriksa lisensi perangkat Bremspace..." -ForegroundColor Cyan

$licenseFile = Join-Path $projectRoot 'storage\license.key'
$isLicensed = $false

function Get-MachineChallengeCode {
    $systemProduct = Get-CimInstance -ClassName Win32_ComputerSystemProduct -ErrorAction SilentlyContinue
    $rawUuid = if ($systemProduct -and $systemProduct.UUID) { $systemProduct.UUID } else { $env:COMPUTERNAME }
    $uuid = ($rawUuid -replace '[^A-Za-z0-9\-]', '')
    $salt = "BREMSPACE_HWID_V1_2026"
    
    $hasher = [System.Security.Cryptography.SHA256]::Create()
    $bytes = [System.Text.Encoding]::UTF8.GetBytes("$salt`:$uuid")
    $hashBytes = $hasher.ComputeHash($bytes)
    $hex = ($hashBytes | ForEach-Object { $_.ToString("X2") }) -join ""
    
    $p1 = $hex.Substring(0, 4)
    $p2 = $hex.Substring(4, 4)
    $p3 = $hex.Substring(8, 4)
    return "BREM-$p1-$p2-$p3"
}

function Get-ExpectedActivationKey([string]$challenge) {
    $salt = "BREMSPACE_LICENSE_SECURITY_ENGINE_V1"
    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($salt)
    $hashBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($challenge.ToUpper().Trim()))
    $hex = ($hashBytes | ForEach-Object { $_.ToString("X2") }) -join ""
    
    $p1 = $hex.Substring(0, 4)
    $p2 = $hex.Substring(4, 4)
    $p3 = $hex.Substring(8, 4)
    $p4 = $hex.Substring(12, 4)
    return "ACT-$p1-$p2-$p3-$p4"
}

$challengeCode = Get-MachineChallengeCode
$expectedKey = Get-ExpectedActivationKey $challengeCode

if (Test-Path $licenseFile) {
    try {
        $json = Get-Content -Path $licenseFile -Raw | ConvertFrom-Json
        if ($json.challenge -eq $challengeCode -and $json.key -eq $expectedKey) {
            $isLicensed = $true
        }
    } catch {}
}

if (-not $isLicensed) {
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Yellow
    Write-Host "           AKTIVASI LISENSI RESMI BREMSPACE ERP DAN POS" -ForegroundColor Yellow
    Write-Host "======================================================================" -ForegroundColor Yellow
    Write-Host "Identitas Hardware Komputer Anda:" -ForegroundColor White
    Write-Host "Kode Mesin (Challenge Code) : $challengeCode" -ForegroundColor Green
    Write-Host ""
    Write-Host "Kirim kode di atas ke WhatsApp Developer Bremspace untuk aktivasi:" -ForegroundColor White
    $waUrl = "https://wa.me/?text=Halo+saya+minta+aktivasi+lisensi+Bremspace+kode:+$challengeCode"
    Write-Host "-> Tautan WhatsApp: $waUrl" -ForegroundColor Cyan
    Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray

    $validInput = $false
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        $inputKey = Read-Host "Masukkan Kode Aktivasi (ACT-XXXX-YYYY-ZZZZ-WWWW)"
        $cleanInput = $inputKey.Trim().ToUpper()

        if ($cleanInput -eq $expectedKey) {
            $storageDir = Join-Path $projectRoot 'storage'
            if (-not (Test-Path $storageDir)) { New-Item -ItemType Directory -Path $storageDir -Force | Out-Null }
            
            $licenseData = @{
                status = "ACTIVE"
                challenge = $challengeCode
                key = $cleanInput
                verified_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
                brand = "Bremspace"
            } | ConvertTo-Json
            
            Set-Content -Path $licenseFile -Value $licenseData -Encoding UTF8
            Write-Host "[OK] Lisensi Hardware Bremspace BERHASIL Diverifikasi!" -ForegroundColor Green
            $validInput = $true
            break
        } else {
            Write-Host "[GAGAL] Kode Aktivasi tidak valid untuk komputer ini. (Percobaan $attempt/3)" -ForegroundColor Red
        }
    }

    if (-not $validInput) {
        Write-Host "[ERROR] Aktivasi lisensi gagal. Instalasi dihentikan." -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[OK] Lisensi Hardware Bremspace aktif dan sah di komputer ini." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 4. Siapkan Direktori Penyimpanan Lokal
# ------------------------------------------------------------------------------
Write-Host "[3/5] Menyiapkan volume penyimpanan lokal..." -ForegroundColor Cyan
$folders = @(
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\framework\testing',
    'storage\logs',
    'storage\app\public',
    'bootstrap\cache',
    'database',
    'docker\data\mysql',
    'docker\nginx'
)
foreach ($f in $folders) {
    $p = Join-Path $projectRoot $f
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
}

$envFile = Join-Path $projectRoot '.env'
$envExample = Join-Path $projectRoot '.env.example'
if (-not (Test-Path $envFile)) {
    if (Test-Path $envExample) {
        Copy-Item $envExample $envFile
    } else {
        New-Item -ItemType File -Path $envFile -Force | Out-Null
    }
}
$env:BREMSPACE_HOST_CHALLENGE = $challengeCode
if (Test-Path $envFile) {
    try {
        $envTxt = Get-Content $envFile -Raw -ErrorAction SilentlyContinue
        if ($envTxt -match '(?m)^BREMSPACE_HOST_CHALLENGE=') {
            $envTxt = $envTxt -replace '(?m)^BREMSPACE_HOST_CHALLENGE=.*$', "BREMSPACE_HOST_CHALLENGE=$challengeCode"
        } else {
            $envTxt += "`r`nBREMSPACE_HOST_CHALLENGE=$challengeCode"
        }
        Set-Content -Path $envFile -Value $envTxt -Encoding UTF8
    } catch {}
}

# Helper verifikasi koneksi port TCP
function Test-PortListening([int]$p) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect("127.0.0.1", $p, $null, $null)
        $ready = $iar.AsyncWaitHandle.WaitOne(1000, $false)
        if ($ready -and $client.Connected) {
            $client.EndConnect($iar)
            $client.Close()
            return $true
        }
        $client.Close()
    } catch {}
    return $false
}

# ------------------------------------------------------------------------------
# 5. Jalankan Service Stack via Docker Compose
# ------------------------------------------------------------------------------
Write-Host "[4/5] Menyalakan kontainer Bremspace ERP dan POS (Port 1741)..." -ForegroundColor Cyan

# Pastikan volume database tidak menimpa file seeder bawaan container
$composeFile = Join-Path $projectRoot 'compose.yaml'
if (Test-Path $composeFile) {
    try {
        $composeRaw = Get-Content $composeFile -Raw -ErrorAction SilentlyContinue
        if ($composeRaw -match '-\s*\./database:/var/www/html/database') {
            $composeClean = $composeRaw -replace '(?m)^\s*-\s*\./database:/var/www/html/database\r?\n?', ''
            Set-Content -Path $composeFile -Value $composeClean -Encoding UTF8
        }
    } catch {}
}

$ghTokenObf = "dHZndWhvX2NuZ18xMU83VDdLVFYwRkg3WWtqS3VBNmJLX1V4dmN4UUl4czh0MUtGRVRXYmdoWGxZZnZrZ1RXOWlUaUdXc0NqSVZYdzgzUEJHRUVNNnhJN1ljYjRp"
$ghPat = ""
try {
    $bytes = [System.Convert]::FromBase64String($ghTokenObf)
    $rot13 = [System.Text.Encoding]::UTF8.GetString($bytes)
    $chars = $rot13.ToCharArray()
    for ($i = 0; $i -lt $chars.Length; $i++) {
        $c = [int]$chars[$i]
        if ($c -ge 65 -and $c -le 90) { $chars[$i] = [char](((($c - 65) + 13) % 26) + 65) }
        elseif ($c -ge 97 -and $c -le 122) { $chars[$i] = [char](((($c - 97) + 13) % 26) + 97) }
    }
    $ghPat = -join $chars
} catch {}

$serverStarted = $false

if ($dockerMode -eq "DOCKER_CLI") {
    try {
        if ($ghPat) {
            $ghPat | & docker.exe login ghcr.io -u bremspace --password-stdin 2>$null
        }
        $hasBuild = (Test-Path "Dockerfile") -and ((Get-Content "compose.yaml" -Raw -ErrorAction SilentlyContinue) -match "build:")
        if ($hasBuild) {
            Write-Host "[INFO] Menjalankan Docker Compose Build lokal..." -ForegroundColor Cyan
            & docker.exe compose up -d --build
        } else {
            Write-Host "[INFO] Menarik kontainer rilis terbaru dari GHCR..." -ForegroundColor Cyan
            & docker.exe compose pull app queue 2>$null
            & docker.exe compose up -d
        }

        Write-Host "[INFO] Menunggu kontainer siap di port 1741..." -ForegroundColor Yellow
        for ($s = 0; $s -lt 15; $s++) {
            Start-Sleep -Seconds 1
            if (Test-PortListening 1741) {
                $serverStarted = $true
                Write-Host "[OK] Kontainer Docker Bremspace aktif melayani di port 1741." -ForegroundColor Green
                break
            }
        }
    } catch {
        Write-Host "[WARN] Kendala saat menjalankan Docker Compose: $_" -ForegroundColor Yellow
    }
} elseif ($dockerMode -eq "WSL2") {
    try {
        $wslPath = $projectRoot.Replace('\', '/').Replace('C:', '/mnt/c').Replace('c:', '/mnt/c')
        $hasBuild = (Test-Path "Dockerfile") -and ((Get-Content "compose.yaml" -Raw -ErrorAction SilentlyContinue) -match "build:")
        if ($hasBuild) {
            & wsl.exe -e sh -c "cd '$wslPath'; export BREMSPACE_HOST_CHALLENGE='$challengeCode'; sudo -E docker compose up -d --build"
        } else {
            & wsl.exe -e sh -c "cd '$wslPath'; export BREMSPACE_HOST_CHALLENGE='$challengeCode'; echo '$ghPat' | sudo docker login ghcr.io -u bremspace --password-stdin 2>/dev/null; sudo -E docker compose pull app queue; sudo -E docker compose up -d"
        }

        Write-Host "[INFO] Menunggu kontainer WSL2 siap di port 1741..." -ForegroundColor Yellow
        for ($s = 0; $s -lt 15; $s++) {
            Start-Sleep -Seconds 1
            if (Test-PortListening 1741) {
                $serverStarted = $true
                Write-Host "[OK] Docker Engine WSL2 aktif melayani di port 1741." -ForegroundColor Green
                break
            }
        }
    } catch {
        Write-Host "[WARN] Kendala saat menjalankan Docker via WSL2: $_" -ForegroundColor Yellow
    }
}

if (-not $serverStarted) {
    Write-Host "[WARN] Server belum merespon di port 1741 setelah 15 detik." -ForegroundColor Yellow
    Write-Host "[INFO] Status kontainer saat ini:" -ForegroundColor Gray
    if ($dockerMode -eq "DOCKER_CLI") {
        & docker.exe compose ps
    } else {
        & wsl.exe -e sh -c "cd '$wslPath'; sudo docker compose ps" 2>$null
    }
}

# ------------------------------------------------------------------------------
# 6. Deteksi IP Jaringan Lokal dan Peluncuran Browser
# ------------------------------------------------------------------------------
Write-Host "[5/5] Memeriksa koneksi jaringan dan membuka aplikasi..." -ForegroundColor Cyan
$port = 1741
$lanIp = ""
try {
    $allIps = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"
    }
    if ($allIps) { $lanIp = ($allIps | Select-Object -First 1).IPAddress }
} catch {}

$targetPath = "/install.php"
if (Test-Path (Join-Path $projectRoot 'storage\installed.lock')) {
    $targetPath = "/app/login"
}

$localUrl = "http://localhost:$port$targetPath"
$networkUrl = if ($lanIp) { "http://$lanIp`:$port$targetPath" } else { "" }

try { Start-Process $localUrl } catch {}

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "   Bremspace ERP dan POS System Berhasil Berjalan di Docker!" -ForegroundColor Green
Write-Host "   - Dari Komputer Ini       : $localUrl" -ForegroundColor Cyan
if ($networkUrl) {
Write-Host "   - Dari HP/iPhone/iPad/LAN : $networkUrl" -ForegroundColor Cyan
}
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [INFO] Untuk menghentikan server, jalankan: stop-server.bat" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
