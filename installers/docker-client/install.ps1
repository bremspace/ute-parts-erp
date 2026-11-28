param()
$ErrorActionPreference = "Stop"

# =============================================================================
# UTE PARTS ERP - Docker Installer khusus NODE CLIENT (Windows)
# =============================================================================
# Peran : primary_store (default) atau cloud_storefront
# TIDAK menjalankan seeder apa pun. Data awal ditarik dari cloud via sync:bootstrap.
# =============================================================================

$InstallerDir = $PSScriptRoot
$ProjectDir = (Resolve-Path (Join-Path $InstallerDir "../..")).Path
$EnvFile = Join-Path $InstallerDir ".env.client"
$ErrLog = Join-Path $InstallerDir "install_error.log"
if (Test-Path $ErrLog) { Remove-Item $ErrLog -Force }

function Ask-Default([string]$Prompt, [string]$Default) {
    $v = Read-Host $Prompt
    if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
    return $v
}

function Read-Secret([string]$Prompt) {
    $secure = Read-Host $Prompt -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

function New-SyncSecret {
    if (Get-Command openssl -ErrorAction SilentlyContinue) {
        return (openssl rand -hex 32)
    }
    return ([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))
}

function Mask-Secret([string]$Value) {
    if ([string]::IsNullOrWhiteSpace($Value)) { return "(kosong)" }
    if ($Value.Length -le 4) { return "****" }
    return "$($Value.Substring(0,4))****$($Value.Substring($Value.Length - 2))"
}

function Esc-Env([string]$Value) {
    if ($null -eq $Value) { return "" }
    # phpdotenv: backslash -> \\, quote -> \", dollar -> \$ (cegah interpolasi).
    # Backtick tidak perlu di-escape di file .env.
    return $Value.Replace('\','\\').Replace('"','\"').Replace('$','\$')
}

function Get-EnvValue([string]$Key) {
    if (-not (Test-Path $EnvFile)) { return "" }
    $line = Get-Content $EnvFile | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return "" }
    $val = $line.Substring($Key.Length + 1)
    if ($val.StartsWith('"') -and $val.EndsWith('"')) { $val = $val.Substring(1, $val.Length - 2) }
    return $val
}

function Show-Summary {
    $roleLabel = if ($Role -eq "cloud") { "cloud_storefront" } else { "primary_store" }
    $mode = if ($Role -eq "cloud") { "false" } else { "true" }
    Write-Host ""
    Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " RINGKASAN KONFIGURASI (sebelum build)" -ForegroundColor Cyan
    Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " Peran node        : $Role ($roleLabel)"
    Write-Host " SYNC_CLIENT_MODE  : $mode"
    $urlLabel = if ([string]::IsNullOrWhiteSpace($RemoteUrl)) { "(kosong / pasif)" } else { $RemoteUrl }
    Write-Host " URL cloud         : $urlLabel"
    Write-Host " Node ID           : $NodeId"
    Write-Host " API secret        : $(Mask-Secret $ApiSecret)"
    Write-Host " Admin lokal       : $AdminEmail"
    Write-Host " Password admin    : $(Mask-Secret $AdminPassword)"
    $branchLabel = if ([string]::IsNullOrWhiteSpace($BranchCode)) { "(tidak ada)" } else { "$BranchCode - $BranchName" }
    Write-Host " Cabang opsional   : $branchLabel"
    Write-Host " Database          : mysql:3306 / ute_parts_client (volume permanen)"
    Write-Host " Port host         : 80:80"
    Write-Host " Image             : ute-parts-client:latest"
    Write-Host " Seeder            : TIDAK DIJALANKAN (data via sync:bootstrap)"
    Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
}

try {
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "  UTE PARTS ERP - Docker Installer CLIENT NODE (Windows)" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host ""

    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw "Docker tidak ditemukan. Install Docker Desktop lalu jalankan ulang installer."
    }
    docker info | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Docker engine belum berjalan. Jalankan Docker Desktop." }

    $ComposeCmd = "docker compose"
    $composeCheck = & docker compose version 2>$null
    if ($LASTEXITCODE -ne 0) {
        if (Get-Command docker-compose -ErrorAction SilentlyContinue) { $ComposeCmd = "docker-compose" }
        else { throw "Docker Compose belum tersedia." }
    }
    Write-Host "[OK] Docker & Compose terdeteksi." -ForegroundColor Green

    # --------------------------- wizard ---------------------------------------
    if (Test-Path $EnvFile) {
        Write-Host "[INFO] .env.client sudah ada." -ForegroundColor Cyan
        $reuse = Ask-Default "Gunakan konfigurasi yang ada? (Y/n, default Y)" "Y"
        if ($reuse -match '^[Yy]$') {
            $Role = if ((Get-EnvValue "SYNC_NODE_ROLE") -eq "cloud_storefront") { "cloud" } else { "client" }
            $RemoteUrl = Get-EnvValue "SYNC_REMOTE_URL"
            $NodeId = Get-EnvValue "SYNC_NODE_ID"
            $ApiSecret = Get-EnvValue "SYNC_API_SECRET"
            $AdminEmail = Get-EnvValue "ADMIN_EMAIL"
            $AdminPassword = Get-EnvValue "ADMIN_PASSWORD"
            $BranchCode = Get-EnvValue "ADMIN_BRANCH_CODE"
            $BranchName = Get-EnvValue "ADMIN_BRANCH_NAME"
            if ([string]::IsNullOrWhiteSpace($AdminEmail)) { $AdminEmail = "admin@uteparts.id" }
            if ([string]::IsNullOrWhiteSpace($NodeId)) { $NodeId = "STORE-01" }
        } else {
            Copy-Item $EnvFile "$EnvFile.bak.$(Get-Date -Format yyyyMMddHHmmss)" -Force
            $reconfigure = $true
        }
    } else {
        $reconfigure = $true
    }

    if ($reconfigure) {
        Write-Host ""
        Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
        Write-Host " WIZARD KONFIGURASI NODE" -ForegroundColor Cyan
        Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
        Write-Host "Pilih peran node:"
        Write-Host "  [1] client  (primary_store)     - DEFAULT. Toko lokal, data dari cloud."
        Write-Host "  [2] cloud   (cloud_storefront)  - Sumber data kanonik di uteparts.id."
        $rolePrompt = "Peran (1/2, default 1)"
        $roleInput = Ask-Default $rolePrompt "1"
        if ($roleInput -eq "1" -or $roleInput -ieq "client") { $Role = "client" }
        elseif ($roleInput -eq "2" -or $roleInput -ieq "cloud") { $Role = "cloud" }
        else { throw "Peran tidak valid." }

        if ($Role -eq "client") {
            $RemoteUrl = Ask-Default "URL cloud (default: https://uteparts.id)" "https://uteparts.id"
            if ([string]::IsNullOrWhiteSpace($RemoteUrl)) { $RemoteUrl = "https://uteparts.id" }
            $NodeId = Ask-Default "Node ID client (default: STORE-01)" "STORE-01"
        } else {
            Write-Host "[INFO] Node cloud pasif receiver; URL remote boleh kosong." -ForegroundColor Yellow
            $RemoteUrl = Ask-Default "URL cloud (kosongkan / '-' untuk pasif)" ""
            if ($RemoteUrl -eq "-") { $RemoteUrl = "" }
            $NodeId = Ask-Default "Node ID cloud (default: CLOUD-01)" "CLOUD-01"
        }
        if ([string]::IsNullOrWhiteSpace($NodeId)) { $NodeId = "STORE-01" }

        Write-Host ""
        Write-Host "API secret sync HARUS sama persis dengan node cloud (minimal 32 karakter)." -ForegroundColor Yellow
        $ApiSecret = Read-Secret "API secret sync (Enter = generate otomatis)"
        if ([string]::IsNullOrWhiteSpace($ApiSecret)) {
            $ApiSecret = New-SyncSecret
            Write-Host "[INFO] API secret dibuat otomatis. SALIN ke node lawan bicara!" -ForegroundColor Yellow
        } elseif ($ApiSecret.Length -lt 32) {
            Write-Host "[WARN] API secret < 32 karakter. Disarankan lebih panjang." -ForegroundColor Yellow
        }

        Write-Host ""
        $AdminEmail = Ask-Default "Email admin lokal (default: admin@uteparts.id)" "admin@uteparts.id"
        if ([string]::IsNullOrWhiteSpace($AdminEmail)) { $AdminEmail = "admin@uteparts.id" }
        $AdminPassword = Read-Secret "Password admin lokal (Enter = generate otomatis)"
        if ([string]::IsNullOrWhiteSpace($AdminPassword)) {
            $AdminPassword = [guid]::NewGuid().ToString('N').Substring(0,20)
            Write-Host "[INFO] Password admin dibuat otomatis (lihat .env.client)." -ForegroundColor Yellow
        } elseif ($AdminPassword.Length -lt 8) {
            throw "Password admin minimal 8 karakter."
        }

        Write-Host ""
        $BranchCode = Ask-Default "Kode cabang opsional, '-' = tanpa cabang (default: CBG-01)" "CBG-01"
        if ($BranchCode -eq "-") { $BranchCode = "" }
        if (-not [string]::IsNullOrWhiteSpace($BranchCode)) {
            $BranchName = Ask-Default "Nama cabang (default: Cabang Utama)" "Cabang Utama"
            if ([string]::IsNullOrWhiteSpace($BranchName)) { $BranchName = "Cabang Utama" }
        } else { $BranchName = "" }

        # ----------------------- tulis .env.client ---------------------------
        $appName = if ($Role -eq "cloud") { "UTE PARTS ERP - CLOUD" } else { "UTE PARTS ERP - CLIENT" }
        $nodeRole = if ($Role -eq "cloud") { "cloud_storefront" } else { "primary_store" }
        $clientMode = if ($Role -eq "cloud") { "false" } else { "true" }
        $adminName = "Admin $Role"
        $lines = @(
            "# Dibuat oleh installers/docker-client/install.ps1 - JANGAN commit file ini."
            'APP_NAME="' + (Esc-Env $appName) + '"'
            'APP_ENV=production'
            'APP_KEY='
            'APP_DEBUG=false'
            'APP_URL=http://localhost'
            ''
            'APP_LOCALE=id'
            'APP_FALLBACK_LOCALE=en'
            'APP_FAKER_LOCALE=en_US'
            'APP_MAINTENANCE_DRIVER=file'
            'BCRYPT_ROUNDS=12'
            ''
            'LOG_CHANNEL=stack'
            'LOG_STACK=single'
            'LOG_LEVEL=debug'
            ''
            'DB_CONNECTION=mysql'
            'DB_HOST=mysql'
            'DB_PORT=3306'
            'DB_DATABASE=ute_parts_client'
            'DB_USERNAME=ute_client'
            'DB_PASSWORD=ute_client_secret'
            ''
            'SESSION_DRIVER=database'
            'SESSION_LIFETIME=120'
            'SESSION_ENCRYPT=false'
            'SESSION_PATH=/'
            'SESSION_DOMAIN=null'
            'SESSION_SECURE_COOKIE=false'
            ''
            'BROADCAST_CONNECTION=log'
            'FILESYSTEM_DISK=local'
            'QUEUE_CONNECTION=database'
            'CACHE_STORE=database'
            ''
            'MAIL_MAILER=log'
            'MAIL_FROM_ADDRESS="hello@uteparts.id"'
            'MAIL_FROM_NAME="UTE PARTS ERP"'
            'BREMSPACE_LICENSE_ENABLED=false'
            'VITE_APP_NAME="' + (Esc-Env $appName) + '"'
            ''
            '# Hybrid Sync'
            'SYNC_ENABLED=true'
            "SYNC_NODE_ROLE=$nodeRole"
            'SYNC_NODE_ID="' + (Esc-Env $NodeId) + '"'
            'SYNC_REMOTE_URL="' + (Esc-Env $RemoteUrl) + '"'
            'SYNC_API_SECRET="' + (Esc-Env $ApiSecret) + '"'
            'SYNC_PULL_INTERVAL_SECONDS=30'
            "SYNC_CLIENT_MODE=$clientMode"
            ''
            '# Admin LOKAL untuk login awal (bukan seeder). Satu user, dibuat bila belum ada.'
            'ADMIN_EMAIL="' + (Esc-Env $AdminEmail) + '"'
            'ADMIN_PASSWORD="' + (Esc-Env $AdminPassword) + '"'
            'ADMIN_NAME="' + (Esc-Env $adminName) + '"'
            'ADMIN_BRANCH_CODE="' + (Esc-Env $BranchCode) + '"'
            'ADMIN_BRANCH_NAME="' + (Esc-Env $BranchName) + '"'
            'ADMIN_FORCE=false'
        )
        # UTF-8 tanpa BOM agar Dotenv tidak membaca karakter BOM di baris pertama.
        [System.IO.File]::WriteAllText($EnvFile, ($lines -join "`n"), (New-Object System.Text.UTF8Encoding($false)))
        Write-Host "[OK] File .env.client tersimpan." -ForegroundColor Green
    }

    Show-Summary
    $proceed = Ask-Default "Lanjutkan build & jalankan container? (Y/n, default Y)" "Y"
    if ($proceed -match '^[Nn]$') {
        Write-Host "Dibatalkan. File .env.client tetap tersimpan."
        exit 0
    }

    # --------------------------- aksi -----------------------------------------
    Write-Host ""
    Write-Host "Pilih aksi:" -ForegroundColor Yellow
    Write-Host "  [1] Fresh install - hapus container & volume lama (database kosong)"
    Write-Host "  [2] Update / Start - rebuild image, data database tetap"
    $action = Ask-Default "Pilihan (1/2, default 2)" "2"
    if ($action -eq "1") {
        Write-Host "[PERINGATAN] Database lokal (volume mysql) akan dihapus. Data ditarik ulang via sync:bootstrap." -ForegroundColor Yellow
        $confirm = Ask-Default "Yakin? (y/N)" "N"
        if ($confirm -match '^[Yy]$') {
            & docker compose down -v --remove-orphans
            if ($LASTEXITCODE -ne 0) { throw "Gagal membersihkan container lama." }
            Write-Host "[OK] Volume lama dibersihkan." -ForegroundColor Green
        } else { Write-Host "Dibatalkan."; exit 0 }
    } elseif ($action -ne "2") { throw "Pilihan tidak valid." }

    # --------------------------- build ----------------------------------------
    Write-Host "[STEP] Build image ute-parts-client:latest ..." -ForegroundColor Cyan
    & docker build -t ute-parts-client:latest -f (Join-Path $InstallerDir "Dockerfile") $ProjectDir
    if ($LASTEXITCODE -ne 0) { throw "docker build gagal." }

    Write-Host "[STEP] Menjalankan service app + mysql ..." -ForegroundColor Cyan
    & docker compose up -d
    if ($LASTEXITCODE -ne 0) { throw "docker compose up gagal." }

    # --------------------------- firewall opsional ----------------------------
    $openFw = Ask-Default "Buka akses LAN (firewall port 80)? (Y/N, default N)" "N"
    if ($openFw -match '^[Yy]$') {
        $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
        if (-not $isAdmin) {
            Write-Host "[WARN] Jalankan PowerShell sebagai Administrator untuk membuka firewall." -ForegroundColor Yellow
        } else {
            if (-not (Get-NetFirewallRule -DisplayName "UTE PARTS CLIENT HTTP" -ErrorAction SilentlyContinue)) {
                New-NetFirewallRule -DisplayName "UTE PARTS CLIENT HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow | Out-Null
            }
            Write-Host "[OK] Firewall port 80 dibuka." -ForegroundColor Green
        }
    }

    $lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'Loopback|vEthernet|Docker' } |
        Select-Object -First 1 -ExpandProperty IPAddress)
    if ([string]::IsNullOrWhiteSpace($lanIp)) { $lanIp = "IP-LAN-PC-SERVER" }

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Green
    Write-Host "[SUKSES] UTE PARTS ERP CLIENT NODE berjalan di http://localhost" -ForegroundColor Green
    Write-Host "Akses LAN : http://$lanIp/" -ForegroundColor Green
    Write-Host "Login awal: $AdminEmail / (password di .env.client)" -ForegroundColor Green
    Write-Host ""
    Write-Host "LANGKAH PENTING - tarik data dari cloud:" -ForegroundColor Yellow
    Write-Host "  cd `"$InstallerDir`""
    Write-Host "  docker compose exec app php artisan sync:bootstrap"
    Write-Host "  docker compose exec -e ADMIN_FORCE=true app php /usr/local/bin/bootstrap-client-admin.php"
    Write-Host "Cek status sync: http://localhost/app/sync/status?probe=1"
    Write-Host "============================================================" -ForegroundColor Green
} catch {
    Write-Host ""
    Write-Host "[ERROR] $($_.Exception.Message)" -ForegroundColor Red
    Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - $($_.Exception.Message)" | Out-File $ErrLog -Append -Encoding utf8
    $_.ScriptStackTrace | Out-File $ErrLog -Append -Encoding utf8
    Write-Host "[INFO] Detail error: $ErrLog" -ForegroundColor Yellow
}
Read-Host "Tekan Enter untuk keluar"
