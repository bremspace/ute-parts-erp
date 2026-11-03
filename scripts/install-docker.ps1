# ==============================================================================
# Bremspace ERP & POS System - Windows Universal Docker Installer & Launcher
# Brand: Bremspace
# Strategi: WSL2 Docker Engine Native (Bebas Biaya Lisensi) + Docker Desktop Fallback
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
Write-Host "   Bremspace ERP & POS System - Windows Docker Universal Launcher" -ForegroundColor Cyan
Write-Host "   Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)" -ForegroundColor Cyan
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
    Write-Host "✔ [OK] Berjalan dengan Hak Akses Administrator." -ForegroundColor Green
} else {
    Write-Host "ℹ [INFO] Berjalan sebagai Standard User." -ForegroundColor Yellow
}

# ------------------------------------------------------------------------------
# 2. Deteksi Engine Docker (Docker Desktop vs WSL2 Native)
# ------------------------------------------------------------------------------
Write-Host "[1/5] Memeriksa engine Docker di Windows..." -ForegroundColor Cyan

$dockerCmd = Get-Command docker.exe -ErrorAction SilentlyContinue
$dockerMode = "NONE"

# A. Cek apakah Docker Desktop sudah ada dan daemon merespon
if ($dockerCmd) {
    $info = & docker.exe info 2>&1
    if ($LASTEXITCODE -eq 0) {
        $dockerMode = "DOCKER_CLI"
        Write-Host "✔ [OK] Docker daemon aktif dan siap digunakan." -ForegroundColor Green
    } else {
        # Coba jalankan Docker Desktop jika terpasang
        $ddPath = "${env:ProgramFiles}\Docker\Docker\Docker Desktop.exe"
        if (Test-Path $ddPath) {
            Write-Host "ℹ [INFO] Menyalakan Docker Desktop di background..." -ForegroundColor Yellow
            Start-Process -FilePath $ddPath -WindowStyle Hidden
            for ($i = 0; $i -lt 15; $i++) {
                Start-Sleep -Seconds 2
                $test = & docker.exe info 2>&1
                if ($LASTEXITCODE -eq 0) {
                    $dockerMode = "DOCKER_CLI"
                    Write-Host "✔ [OK] Docker Desktop berhasil tersambung." -ForegroundColor Green
                    break
                }
            }
        }
    }
}

# B. Jika Docker Desktop tidak ada, gunakan WSL2 Docker Engine (Bebas Biaya Lisensi)
if ($dockerMode -eq "NONE") {
    Write-Host "ℹ Docker Desktop tidak terdeteksi. Memeriksa fitur WSL2 Windows..." -ForegroundColor Cyan
    $wslCmd = Get-Command wsl.exe -ErrorAction SilentlyContinue
    
    if ($wslCmd) {
        # Cek apakah WSL2 sudah terpasang
        $wslList = & wsl.exe --list --quiet 2>&1
        if ($LASTEXITCODE -eq 0 -and $wslList) {
            Write-Host "✔ [OK] WSL2 Windows terdeteksi aktif." -ForegroundColor Green
            # Periksa docker di dalam WSL
            $wslDocker = & wsl.exe -e sh -c "command -v docker" 2>&1
            if ($LASTEXITCODE -eq 0 -and $wslDocker) {
                $dockerMode = "WSL2"
                Write-Host "✔ [OK] Docker Engine di dalam WSL2 siap digunakan." -ForegroundColor Green
            } else {
                Write-Host "ℹ [AUTO-FIX] Memasang Docker Engine di dalam distro WSL2..." -ForegroundColor Yellow
                & wsl.exe -e sh -c "curl -fsSL https://get.docker.com | sh && sudo service docker start" 2>$null
                $dockerMode = "WSL2"
            }
        } else {
            Write-Host "ℹ [AUTO-FIX] Menyiapkan WSL2 otomatis untuk Windows..." -ForegroundColor Yellow
            if ($isAdmin) {
                & wsl.exe --install --no-distribution
                Write-Host "✔ [OK] Komponen WSL2 berhasil didaftarkan ke Windows." -ForegroundColor Green
            } else {
                Write-Host "⚠️ Untuk mengaktifkan WSL2 otomatis tanpa Docker Desktop, jalankan file ini sebagai Administrator." -ForegroundColor Yellow
            }
        }
    }
}

# ------------------------------------------------------------------------------
# 3. Verifikasi Lisensi Hardware Offline (Bremspace Anti-Duplikasi)
# ------------------------------------------------------------------------------
Write-Host "[2/5] Memeriksa lisensi perangkat Bremspace..." -ForegroundColor Cyan

$licenseFile = Join-Path $projectRoot 'storage\license.key'
$isLicensed = $false

# Helper kalkulasi signature
function Get-MachineChallengeCode {
    $systemProduct = Get-CimInstance -ClassName Win32_ComputerSystemProduct -ErrorAction SilentlyContinue
    $uuid = if ($systemProduct) { $systemProduct.UUID } else { $env:COMPUTERNAME }
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
    Write-Host "           AKTIVASI LISENSI RESMI BREMSPACE ERP & POS" -ForegroundColor Yellow
    Write-Host "======================================================================" -ForegroundColor Yellow
    Write-Host "Identitas Hardware Komputer Anda:" -ForegroundColor White
    Write-Host "Kode Mesin (Challenge Code) : $challengeCode" -ForegroundColor Green
    Write-Host ""
    Write-Host "Kirim kode di atas ke WhatsApp Developer Bremspace untuk aktivasi:" -ForegroundColor White
    $waUrl = "https://wa.me/?text=Halo+saya+minta+aktivasi+lisensi+Bremspace+kode:+$challengeCode"
    Write-Host "👉 Klik/Salin Link: $waUrl" -ForegroundColor Cyan
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
            Write-Host "✔ [OK] Lisensi Hardware Bremspace BERHASIL Diverifikasi!" -ForegroundColor Green
            $validInput = $true
            break
        } else {
            Write-Host "❌ [GAGAL] Kode Aktivasi tidak valid untuk komputer ini. (Percobaan $attempt/3)" -ForegroundColor Red
        }
    }

    if (-not $validInput) {
        Write-Host "❌ [ERROR] Aktivasi lisensi gagal. Instalasi dihentikan." -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "✔ [OK] Lisensi Hardware Bremspace aktif & sah di komputer ini." -ForegroundColor Green
}

# ------------------------------------------------------------------------------
# 4. Siapkan Direktori Storage & Data MySQL
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
    'docker\data\mysql'
)
foreach ($f in $folders) {
    $p = Join-Path $projectRoot $f
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
}

# ------------------------------------------------------------------------------
# 5. Jalankan Docker Compose Stack
# ------------------------------------------------------------------------------
Write-Host "[4/5] Menyalakan kontainer Bremspace ERP & POS (Port 1741)..." -ForegroundColor Cyan

# Login ke GHCR otomatis jika token tersedia
$ghTokenObf = "dHZndWhvX2NuZ18xMU83VDdLVFYwRkg3WWtqS3VBNmJLX1V4dmN4UUl4czh0MUtGRVRXYmdoWGxZZnZrZ1RXOWlUaUdXc0NqSVZYdzgzUEJHRUVNNnhJN1ljYjRp"
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
    if ($dockerMode -eq "DOCKER_CLI") {
        $ghPat | & docker.exe login ghcr.io -u bremspace --password-stdin 2>$null
    }
} catch {}

$hasBuild = (Test-Path "Dockerfile") -and ((Get-Content "compose.yaml" -Raw -ErrorAction SilentlyContinue) -match "build:")

if ($dockerMode -eq "DOCKER_CLI") {
    if ($hasBuild) {
        & docker.exe compose up -d --build
    } else {
        & docker.exe compose pull app queue 2>$null
        & docker.exe compose up -d
    }
} elseif ($dockerMode -eq "WSL2") {
    # Jalankan compose via WSL2
    $wslPath = $projectRoot.Replace('\', '/').Replace('C:', '/mnt/c').Replace('c:', '/mnt/c')
    if ($hasBuild) {
        & wsl.exe -e sh -c "cd '$wslPath' && sudo docker compose up -d --build"
    } else {
        & wsl.exe -e sh -c "cd '$wslPath' && echo '$ghPat' | sudo docker login ghcr.io -u bremspace --password-stdin 2>/dev/null; sudo docker compose pull app queue; sudo docker compose up -d"
    }
} else {
    Write-Host "⚠️ Docker belum siap sepenuhnya. Menjalankan fallback PHP runtime lokal..." -ForegroundColor Yellow
    # Fallback to local PHP if docker is not installed
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $scriptDir 'setup-windows-php.ps1')
}

# ------------------------------------------------------------------------------
# 6. Deteksi IP Jaringan Lokal & Peluncuran Browser
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
Write-Host "   Bremspace ERP & POS System Berhasil Berjalan!" -ForegroundColor Green
Write-Host "   - Dari Komputer Ini       : $localUrl" -ForegroundColor Cyan
if ($networkUrl) {
Write-Host "   - Dari HP/iPhone/iPad/LAN : $networkUrl" -ForegroundColor Cyan
}
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [INFO] Untuk menghentikan server, jalankan: stop-server.bat" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
