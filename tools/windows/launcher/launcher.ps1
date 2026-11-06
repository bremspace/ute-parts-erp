<#
.SYNOPSIS
    Bremspace ERP Desktop Launcher (PowerShell fallback/development runner)
#>

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BaseDir = Split-Path -Parent (Split-Path -Parent $ScriptDir)

# Ensure single instance
$Mutex = New-Object System.Threading.Mutex($false, "Global\BremspaceERP_SingleInstance_Mutex")
if (-not $Mutex.WaitOne(0, $false)) {
    Write-Warning "[Bremspace] Aplikasi sudah berjalan."
    Exit 0
}

$LocalAppData = [Environment]::GetFolderPath("LocalApplicationData")
$DataDir = Join-Path $LocalAppData "BremspaceERP\db_data"
$ConfigDir = Join-Path $LocalAppData "BremspaceERP\config"
$StorageDir = Join-Path $LocalAppData "BremspaceERP\storage"

New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
New-Item -ItemType Directory -Force -Path $ConfigDir | Out-Null
New-Item -ItemType Directory -Force -Path $StorageDir | Out-Null

# Port Check
function Test-PortAvailable([int]$port) {
    try {
        $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $port)
        $listener.Start()
        $listener.Stop()
        return $true
    } catch {
        return $false
    }
}

$DbPort = 13307
while (-not (Test-PortAvailable $DbPort) -and $DbPort -le 13320) {
    $DbPort++
}

$AppPort = 1741
while (-not (Test-PortAvailable $AppPort) -and $AppPort -le 1755) {
    $AppPort++
}

Write-Host "==> DB Port: $DbPort | App Port: $AppPort" -ForegroundColor Cyan

# Engine directories
$EngineDir = Join-Path $BaseDir "binaries\mariadb"
$FrankPhpExe = Join-Path $BaseDir "binaries\frankphp\frankphp.exe"
$AppDir = $BaseDir

# Prepare my.ini
$MyIniTemplate = Join-Path $BaseDir "tools\windows\config\my.ini.template"
$MyIniPath = Join-Path $ConfigDir "my.ini"
if (Test-Path $MyIniTemplate) {
    $content = Get-Content $MyIniTemplate -Raw
    $content = $content.Replace("{{DB_PORT}}", "$DbPort")
    $content = $content.Replace("{{ENGINE_DIR}}", $EngineDir.Replace("\", "/"))
    $content = $content.Replace("{{DATA_DIR}}", $DataDir.Replace("\", "/"))
    $content = $content.Replace("{{TMP_DIR}}", (Join-Path $DataDir "tmp").Replace("\", "/"))
    Set-Content -Path $MyIniPath -Value $content -Force
}

# First run DB Init
$Items = Get-ChildItem -Path $DataDir -ErrorAction SilentlyContinue
if (-not $Items) {
    Write-Host "==> Initializing MariaDB data directory..." -ForegroundColor Yellow
    $InstallDb = Join-Path $EngineDir "bin\mysql_install_db.exe"
    if (Test-Path $InstallDb) {
        Start-Process -FilePath $InstallDb -ArgumentList "--datadir=`"$DataDir`" --default-storage-engine=InnoDB" -NoNewWindow -Wait
    }
}

# Start MariaDB
Write-Host "==> Starting MariaDB Portable..." -ForegroundColor Green
$MysqldExe = Join-Path $EngineDir "bin\mysqld.exe"
$DbProcess = Start-Process -FilePath $MysqldExe -ArgumentList "--defaults-file=`"$MyIniPath`"" -WindowStyle Hidden -PassThru

# Wait for MariaDB port
$Timeout = 15
$Connected = $false
for ($i = 0; $i -lt $Timeout; $i++) {
    try {
        $tcp = New-Object System.Net.Sockets.TcpClient("127.0.0.1", $DbPort)
        $tcp.Close()
        $Connected = $true
        break
    } catch {
        Start-Sleep -Seconds 1
    }
}

# Start FrankPHP
Write-Host "==> Starting FrankPHP server..." -ForegroundColor Green
$CaddyTemplate = Join-Path $BaseDir "tools\windows\config\Caddyfile.desktop"
$CaddyfilePath = Join-Path $ConfigDir "Caddyfile"
if (Test-Path $CaddyTemplate) {
    $cContent = Get-Content $CaddyTemplate -Raw
    $cContent = $cContent.Replace("{{APP_PORT}}", "$AppPort")
    $cContent = $cContent.Replace("{{APP_DIR}}", $AppDir.Replace("\", "/"))
    Set-Content -Path $CaddyfilePath -Value $cContent -Force
}

$FrankProcess = Start-Process -FilePath $FrankPhpExe -ArgumentList "run --config `"$CaddyfilePath`"" -WindowStyle Hidden -PassThru

# Launch Edge App Mode
$AppUrl = "http://127.0.0.1:$AppPort"
Write-Host "==> Opening Application: $AppUrl" -ForegroundColor Cyan
$EdgePaths = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "${env:ProgramFiles}\Microsoft\Edge\Application\msedge.exe",
    "${env:LOCALAPPDATA}\Microsoft\Edge\Application\msedge.exe"
)
$EdgeExe = $EdgePaths | Where-Object { Test-Path $_ } | Select-Object -First 1

try {
    if ($EdgeExe) {
        $UiProcess = Start-Process -FilePath $EdgeExe -ArgumentList "--app=$AppUrl --window-size=1280,800" -PassThru -Wait
    } else {
        Start-Process $AppUrl
        Write-Host "Tekan sembarang tombol untuk menutup server..." -ForegroundColor Yellow
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    }
} finally {
    Write-Host "==> Graceful cleanup of processes..." -ForegroundColor Yellow
    $MysqlAdmin = Join-Path $EngineDir "bin\mysqladmin.exe"
    if (Test-Path $MysqlAdmin) {
        Start-Process -FilePath $MysqlAdmin -ArgumentList "-u root -P$DbPort shutdown" -WindowStyle Hidden -Wait
    }
    if ($DbProcess -and -not $DbProcess.HasExited) {
        Stop-Process -Id $DbProcess.Id -Force -ErrorAction SilentlyContinue
    }
    if ($FrankProcess -and -not $FrankProcess.HasExited) {
        Stop-Process -Id $FrankProcess.Id -Force -ErrorAction SilentlyContinue
    }
    $Mutex.ReleaseMutex()
    $Mutex.Dispose()
    Write-Host "==> Selesai." -ForegroundColor Green
}
