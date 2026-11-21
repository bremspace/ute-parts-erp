param()
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$GitUrl = "https://github.com/bremspace/ute-parts-erp"
$GitBranch = "main"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  UTE PARTS ERP - Docker Installer (Windows)" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Pilih sumber instalasi:" -ForegroundColor Yellow
Write-Host "  [1] Gunakan folder lokal (default) - offline"
Write-Host "  [2] Git pull/clone dari URL - online"
$choice = Read-Host "Pilihan (1/2, default 1)"
if ([string]::IsNullOrWhiteSpace($choice)) { $choice = "1" }

if ($choice -eq "2") {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        throw "Git tidak ditemukan. Install Git for Windows."
    }
    if (Test-Path (Join-Path $ProjectDir ".git")) {
        Write-Host "[INFO] Menarik update terbaru dari Git..." -ForegroundColor Cyan
        git -C $ProjectDir fetch origin
        if ($LASTEXITCODE -ne 0) { throw "git fetch gagal." }
        git -C $ProjectDir pull origin $GitBranch
        if ($LASTEXITCODE -ne 0) { throw "git pull gagal." }
    } else {
        Write-Host "[INFO] Clone dari $GitUrl ..." -ForegroundColor Cyan
        git clone --branch $GitBranch $GitUrl $ProjectDir
        if ($LASTEXITCODE -ne 0) { throw "git clone gagal." }
    }
    Write-Host "[INFO] Source siap." -ForegroundColor Green
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker tidak ditemukan. Install Docker Desktop."
}
docker info | Out-Null

Write-Host "[INFO] Building dan menjalankan container..." -ForegroundColor Cyan
docker compose up -d --build --force-recreate app
if ($LASTEXITCODE -ne 0) { throw "Gagal menjalankan docker compose." }

Write-Host "[SUKSES] UTE_PARTS_ERP berjalan di http://localhost" -ForegroundColor Green