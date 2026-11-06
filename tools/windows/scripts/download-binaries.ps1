<#
.SYNOPSIS
    Downloads Windows x64 portable runtimes (FrankPHP, MariaDB, WebView2) on Windows.
#>

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BaseDir = Split-Path -Parent $ScriptDir
$DestDir = Join-Path $BaseDir "binaries"

New-Item -ItemType Directory -Force -Path (Join-Path $DestDir "frankphp") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $DestDir "mariadb") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $DestDir "webview2") | Out-Null

Write-Host "==> [1/3] Downloading FrankPHP Windows x64..." -ForegroundColor Cyan
$FrankPhpUrl = "https://github.com/dunglas/frankphp/releases/latest/download/frankphp-windows-x86_64.zip"
$FrankPhpZip = Join-Path $DestDir "frankphp.zip"
Invoke-WebRequest -Uri $FrankPhpUrl -OutFile $FrankPhpZip
Expand-Archive -Path $FrankPhpZip -DestinationPath (Join-Path $DestDir "frankphp") -Force
Remove-Item -Force $FrankPhpZip
Write-Host "    FrankPHP downloaded successfully." -ForegroundColor Green

Write-Host "==> [2/3] Downloading MariaDB Portable 11.4 LTS Windows x64..." -ForegroundColor Cyan
$MariaDbVersion = "11.4.5"
$MariaDbUrl = "https://archive.mariadb.org/mariadb-$MariaDbVersion/winx64-packages/mariadb-$MariaDbVersion-winx64.zip"
$MariaDbZip = Join-Path $DestDir "mariadb.zip"
Invoke-WebRequest -Uri $MariaDbUrl -OutFile $MariaDbZip

Write-Host "    Extracting minimal MariaDB runtime..." -ForegroundColor Cyan
$TempExtract = Join-Path $DestDir "mariadb_extracted"
Expand-Archive -Path $MariaDbZip -DestinationPath $TempExtract -Force
$SubDir = Get-ChildItem -Path $TempExtract -Directory | Select-Object -First 1

$MariaDbBin = Join-Path (Join-Path $DestDir "mariadb") "bin"
$MariaDbShare = Join-Path (Join-Path $DestDir "mariadb") "share"
New-Item -ItemType Directory -Force -Path $MariaDbBin | Out-Null
New-Item -ItemType Directory -Force -Path $MariaDbShare | Out-Null

Copy-Item (Join-Path $SubDir.FullName "bin\mysqld.exe") -Destination $MariaDbBin -Force
Copy-Item (Join-Path $SubDir.FullName "bin\mysqladmin.exe") -Destination $MariaDbBin -Force
Copy-Item (Join-Path $SubDir.FullName "bin\mysql.exe") -Destination $MariaDbBin -Force
Copy-Item (Join-Path $SubDir.FullName "bin\mysql_install_db.exe") -Destination $MariaDbBin -Force
Get-ChildItem -Path (Join-Path $SubDir.FullName "bin") -Filter "*.dll" | Copy-Item -Destination $MariaDbBin -Force
Copy-Item (Join-Path $SubDir.FullName "share\english") -Destination $MariaDbShare -Recurse -Force
Copy-Item (Join-Path $SubDir.FullName "share\charsets") -Destination $MariaDbShare -Recurse -Force

Remove-Item -Recurse -Force $TempExtract
Remove-Item -Force $MariaDbZip
Write-Host "    MariaDB minimal runtime prepared successfully." -ForegroundColor Green

Write-Host "==> [3/3] Downloading Microsoft Edge WebView2 Evergreen Bootstrapper..." -ForegroundColor Cyan
$WebView2Url = "https://go.microsoft.com/fwlink/p/?LinkId=2124703"
$WebView2Exe = Join-Path (Join-Path $DestDir "webview2") "MicrosoftEdgeWebview2Setup.exe"
Invoke-WebRequest -Uri $WebView2Url -OutFile $WebView2Exe
Write-Host "    WebView2 bootstrapper downloaded successfully." -ForegroundColor Green

Write-Host "==> All Windows desktop binaries ready in: $DestDir" -ForegroundColor Green
