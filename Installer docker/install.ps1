param()
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

try {
    $errLog = Join-Path $PSScriptRoot "install_error.log"
    if (Test-Path $errLog) { Remove-Item $errLog -Force }
    $ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
    $DefaultGitUrl = "https://github.com/bremspace/ute-parts-erp"

    $existingContainer = docker ps -a --filter "name=UTE_PARTS_ERP" --format "{{.Names}}" 2>$null
    if ($existingContainer) {
        Write-Host "[INFO] Container UTE_PARTS_ERP sudah ada di Docker." -ForegroundColor Yellow
        Write-Host "       Pilihan Fresh install akan menghapus data lama." -ForegroundColor Yellow
    }
    $DefaultGitBranch = "main"

    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "  UTE PARTS ERP - Docker Installer (Windows)" -ForegroundColor Cyan
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Pilih aksi:" -ForegroundColor Yellow
    Write-Host "  [1] Fresh install - hapus data lama, install ulang"
    Write-Host "  [2] Update - pull terbaru + rebuild, data tetap"
    $action = Read-Host "Pilihan (1/2, default 1)"
    if ([string]::IsNullOrWhiteSpace($action)) { $action = "1" }
    if ($action -notin @("1","2")) { throw "Pilihan tidak valid." }

    Write-Host ""
    Write-Host "Pilih sumber:" -ForegroundColor Yellow
    Write-Host "  [1] Git - clone/pull dari URL"
    Write-Host "  [2] Folder lokal - offline, tanpa git"
    $source = Read-Host "Pilihan (1/2, default 2)"
    if ([string]::IsNullOrWhiteSpace($source)) { $source = "2" }
    if ($source -notin @("1","2")) { throw "Pilihan tidak valid." }

    $GitUrl = $DefaultGitUrl
    $GitBranch = $DefaultGitBranch

    if ($source -eq "1") {
        $inputUrl = Read-Host "Link Git (default $DefaultGitUrl)"
        if (-not [string]::IsNullOrWhiteSpace($inputUrl)) { $GitUrl = $inputUrl }

        Write-Host ""
        if (Test-Path (Join-Path $ProjectDir ".git")) {
            Write-Host "[INFO] Mengambil daftar branch dari repo lokal..." -ForegroundColor Cyan
            git -C $ProjectDir fetch origin | Out-Null
            $branches = git -C $ProjectDir for-each-ref --format="%(refname:short)" refs/remotes/origin |
                Where-Object { $_ -notmatch 'HEAD' -and $_ -ne 'origin' } |
                ForEach-Object { $_ -replace '^origin/','' }
        } else {
            Write-Host "[INFO] Mengambil daftar branch dari remote..." -ForegroundColor Cyan
            $branches = git ls-remote --heads $GitUrl 2>$null |
                ForEach-Object { ($_ -split '\s+')[1] -replace '^refs/heads/','' }
        }

        if ($branches) {
            Write-Host "Branch yang tersedia:"
            $i = 1
            foreach ($b in $branches) {
                Write-Host "  [$i] $b"
                $i++
            }
        } else {
            Write-Host "[WARN] Tidak dapat mengambil daftar branch. Pakai default." -ForegroundColor Yellow
        }

        $inputBranch = Read-Host "Ketik nama branch atau nomor dari daftar (default $DefaultGitBranch)"
        if ([string]::IsNullOrWhiteSpace($inputBranch)) {
            $GitBranch = $DefaultGitBranch
        } elseif ($inputBranch -match '^\d+$') {
            $idx = [int]$inputBranch - 1
            if ($branches -and $idx -ge 0 -and $idx -lt $branches.Count) {
                $GitBranch = $branches[$idx]
            } else {
                $GitBranch = $inputBranch
            }
        } else {
            $GitBranch = $inputBranch
        }
        if ($GitBranch -ieq "unas") { $GitBranch = "Unas" }
        Write-Host "[INFO] Branch terpilih: $GitBranch" -ForegroundColor Cyan
    }

    if ($action -eq "1") {
        Write-Host "[STEP 1] Fresh install: menghapus container dan volume lama..." -ForegroundColor Cyan
        docker compose down -v
        if ($LASTEXITCODE -ne 0) { throw "Gagal docker compose down." }
        Write-Host "[OK] Container dan volume lama dihapus." -ForegroundColor Green
    }

    if ($source -eq "1") {
        Write-Host "[STEP 2] Update dari Git..." -ForegroundColor Cyan
        if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
            throw "Git tidak ditemukan."
        }
        if (Test-Path (Join-Path $ProjectDir ".git")) {
            Write-Host "[INFO] Menarik update terbaru dari Git..." -ForegroundColor Cyan
            git -C $ProjectDir fetch origin
            if ($LASTEXITCODE -ne 0) { throw "git fetch gagal." }
            git -C $ProjectDir pull origin $GitBranch --progress
            if ($LASTEXITCODE -ne 0) { git -C $ProjectDir branch -r; throw "git pull gagal." }
            Write-Host "[OK] Update Git selesai." -ForegroundColor Green
        } else {
            if ((Get-ChildItem -Force $ProjectDir | Where-Object { $_.Name -ne 'Installer docker' }) -and -not (Test-Path (Join-Path $ProjectDir ".git"))) {
                throw "Folder project tidak kosong dan bukan repo Git. Kosongkan folder atau pilih sumber Folder lokal."
            }
            Write-Host "[INFO] Clone dari $GitUrl ..." -ForegroundColor Cyan
            git clone --branch $GitBranch --progress $GitUrl $ProjectDir
            if ($LASTEXITCODE -ne 0) { throw "git clone gagal." }
            Write-Host "[OK] Clone selesai." -ForegroundColor Green
        }
    } else {
        Write-Host "[STEP 2] Menggunakan folder lokal (offline). Data tetap." -ForegroundColor Cyan
    }

    if (-not (Test-Path (Join-Path $ProjectDir "composer.json"))) {
        throw "Folder project tidak lengkap: composer.json tidak ditemukan di $ProjectDir. Pastikan seluruh folder repo disalin (bukan hanya folder Installer)."
    }

    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw "Docker tidak ditemukan. Install Docker Desktop."
    }
    docker info | Out-Null

    Write-Host "[STEP 3] Building dan menjalankan container..." -ForegroundColor Cyan
    docker compose up -d --build --force-recreate app
    if ($LASTEXITCODE -ne 0) { throw "Gagal menjalankan docker compose." }
    Write-Host "[OK] Container berjalan." -ForegroundColor Green

    $openFw = Read-Host "Buka akses LAN (firewall port 80) agar PC client lain bisa akses? (Y/N, default N)"
    if ($openFw -match '^[Yy]$') {
        $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
        if (-not $isAdmin) {
            Write-Host "[WARN] Bukan Administrator. Jalankan PowerShell sebagai Administrator untuk membuka firewall." -ForegroundColor Yellow
        } else {
            if (-not (Get-NetFirewallRule -DisplayName "UTE PARTS ERP HTTP" -ErrorAction SilentlyContinue)) {
                New-NetFirewallRule -DisplayName "UTE PARTS ERP HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow | Out-Null
            }
            Write-Host "[INFO] Firewall port 80 dibuka untuk LAN." -ForegroundColor Green
        }
    }

    Write-Host "============================================================" -ForegroundColor Green
    Write-Host "[SUKSES] UTE_PARTS_ERP berjalan di http://localhost" -ForegroundColor Green
    Write-Host "Akses dari PC client lain: http://IP-LAN-PC-SERVER/" -ForegroundColor Green
    Write-Host "============================================================" -ForegroundColor Green
} catch {
    Write-Host ""
    Write-Host "[ERROR] $($_.Exception.Message)" -ForegroundColor Red
    Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    $errLog = Join-Path $PSScriptRoot "install_error.log"
    "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - $($_.Exception.Message)" | Out-File $errLog -Append -Encoding utf8
    $_.ScriptStackTrace | Out-File $errLog -Append -Encoding utf8
    Write-Host "[INFO] Detail error disimpan di: $errLog" -ForegroundColor Yellow
}
Read-Host "Tekan Enter untuk keluar"