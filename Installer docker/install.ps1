param()
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker tidak ditemukan. Install Docker Desktop."
}
docker info | Out-Null

Write-Host "[INFO] Building dan menjalankan container..." -ForegroundColor Cyan
docker compose up -d --build --force-recreate app
if ($LASTEXITCODE -ne 0) { throw "Gagal menjalankan docker compose." }

Write-Host "[SUKSES] UTE_PARTS_ERP berjalan di http://localhost" -ForegroundColor Green