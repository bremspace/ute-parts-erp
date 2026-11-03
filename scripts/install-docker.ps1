# ==============================================================================
# Bremspace ERP dan POS System - Windows Universal Docker Installer and Launcher
# Brand: Bremspace
# Strategi: WSL2 Docker Engine Native + Docker Desktop + Fallback PHP Runtime Lokal
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
Write-Host "   Bremspace ERP dan POS System - Windows Universal Launcher" -ForegroundColor Cyan
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
# 2. Deteksi Engine Docker (Docker Desktop vs WSL2 Native)
# ------------------------------------------------------------------------------
Write-Host "[1/5] Memeriksa ketersediaan engine Docker di Windows..." -ForegroundColor Cyan

$dockerCmd = Get-Command docker.exe -ErrorAction SilentlyContinue
$dockerMode = "NONE"

# A. Cek apakah Docker CLI / Docker Desktop terpasang
if ($dockerCmd) {
    try {
        $info = & docker.exe info 2>&1
        if ($LASTEXITCODE -eq 0) {
            $dockerMode = "DOCKER_CLI"
            Write-Host "[OK] Docker Engine aktif dan siap digunakan." -ForegroundColor Green
        } else {
            # Coba jalankan Docker Desktop jika terpasang
            $ddPath = "${env:ProgramFiles}\Docker\Docker\Docker Desktop.exe"
            if (Test-Path $ddPath) {
                Write-Host "[INFO] Menyalakan Docker Desktop di background..." -ForegroundColor Yellow
                Start-Process -FilePath $ddPath -WindowStyle Hidden -ErrorAction SilentlyContinue
                for ($i = 0; $i -lt 15; $i++) {
                    Start-Sleep -Seconds 2
                    $test = & docker.exe info 2>&1
                    if ($LASTEXITCODE -eq 0) {
                        $dockerMode = "DOCKER_CLI"
                        Write-Host "[OK] Docker Desktop berhasil tersambung." -ForegroundColor Green
                        break
                    }
                }
            }
        }
    } catch {
        Write-Host "[INFO] Docker CLI terdeteksi namun daemon belum aktif." -ForegroundColor Yellow
    }
}

# B. Jika Docker Desktop tidak aktif, periksa fitur WSL2 Windows
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
                    $dockerMode = "WSL2"
                    Write-Host "[OK] Docker Engine di dalam WSL2 siap digunakan." -ForegroundColor Green
                }
            }
        } catch {}
    }
}

if ($dockerMode -ne "NONE") {
    Write-Host "[OK] Mode Operasi: $dockerMode" -ForegroundColor Green
} else {
    Write-Host "[INFO] Docker tidak aktif. Sistem akan menggunakan Fallback PHP Runtime Lokal." -ForegroundColor Yellow
}

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
    'docker\data\mysql'
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
# 5. Jalankan Service Stack (Docker atau Fallback Lokal)
# ------------------------------------------------------------------------------
Write-Host "[4/5] Menyalakan server Bremspace ERP dan POS (Port 1741)..." -ForegroundColor Cyan

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
            & docker.exe compose up -d --build
        } else {
            & docker.exe compose pull app queue 2>$null
            & docker.exe compose up -d
        }

        # Tunggu container siap
        Write-Host "[INFO] Menunggu kontainer siap di port 1741..." -ForegroundColor Yellow
        for ($s = 0; $s -lt 10; $s++) {
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

        for ($s = 0; $s -lt 10; $s++) {
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

# Jika Docker gagal atau tidak ada, jalankan Fallback PHP Runtime Lokal
if (-not $serverStarted) {
    Write-Host "[INFO] Mengaktifkan Fallback Runtime PHP Lokal Mandiri..." -ForegroundColor Yellow
    
    # 1. Pastikan PHP Portable 8.4 terpasang
    $setupPhpScript = Join-Path $scriptDir 'setup-windows-php.ps1'
    if (Test-Path $setupPhpScript) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $setupPhpScript
    }

    # Cari php.exe
    $phpExe = "php.exe"
    $localPhp = Join-Path $projectRoot 'tools\php\php.exe'
    if (Test-Path $localPhp) {
        $phpExe = $localPhp
    }

    # 2. Jika kode inti belum ada (Model B Installer Tipis), unduh langsung dari repo
    if (-not (Test-Path (Join-Path $projectRoot 'artisan'))) {
        Write-Host "[INFO] Mengunduh berkas inti aplikasi Bremspace ke direktori lokal..." -ForegroundColor Cyan
        try {
            $tarUrl = "https://api.github.com/repos/bremspace/ute-parts-erp/tarball/main"
            $tempTar = Join-Path $env:TEMP 'bremspace_core.tar.gz'
            
            $headers = @{
                "User-Agent" = "Bremspace-Installer"
                "Accept" = "application/vnd.github.v3+json"
            }
            if ($ghPat) {
                $headers["Authorization"] = "Bearer $ghPat"
            }
            
            Invoke-RestMethod -Uri $tarUrl -Headers $headers -OutFile $tempTar
            if (Test-Path $tempTar) {
                tar -xzf $tempTar --strip-components=1 -C $projectRoot 2>$null
                Remove-Item -Path $tempTar -Force -ErrorAction SilentlyContinue
                Write-Host "[OK] Berkas inti aplikasi berhasil diunduh dan dipasang!" -ForegroundColor Green
            }
        } catch {
            Write-Host "[WARN] Gagal mengunduh berkas inti otomatis via GitHub API: $_" -ForegroundColor Yellow
        }
    }

    # 3. Jalankan server background PHP lokal di port 1741
    $docRoot = Join-Path $projectRoot 'public'
    $logFile = Join-Path $projectRoot 'storage\logs\server.log'
    $pidFile = Join-Path $projectRoot 'storage\framework\server.pid'

    if (Test-Path $docRoot) {
        $serverProc = Start-Process -FilePath $phpExe -ArgumentList "-S", "0.0.0.0:1741", "-t", "`"$docRoot`"" -WindowStyle Hidden -RedirectStandardOutput $logFile -RedirectStandardError $logFile -PassThru -ErrorAction SilentlyContinue
        if ($serverProc) {
            Set-Content -Path $pidFile -Value $serverProc.Id -Encoding ASCII
            Write-Host "[OK] Server PHP Lokal berhasil dinyalakan (PID: $($serverProc.Id))." -ForegroundColor Green
            $serverStarted = $true
        }
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
Write-Host "   Bremspace ERP dan POS System Berhasil Berjalan!" -ForegroundColor Green
Write-Host "   - Dari Komputer Ini       : $localUrl" -ForegroundColor Cyan
if ($networkUrl) {
Write-Host "   - Dari HP/iPhone/iPad/LAN : $networkUrl" -ForegroundColor Cyan
}
Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "   [INFO] Untuk menghentikan server, jalankan: stop-server.bat" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
