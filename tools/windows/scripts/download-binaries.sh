#!/usr/bin/env bash
# ==============================================================================
# Script: download-binaries.sh
# Purpose: Download Windows x64 portable runtimes (PHP 8.4 NTS, Caddy, MariaDB, WebView2)
# ==============================================================================

set -euo pipefail

DEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/binaries"
mkdir -p "${DEST_DIR}/php"
mkdir -p "${DEST_DIR}/caddy"
mkdir -p "${DEST_DIR}/mariadb"
mkdir -p "${DEST_DIR}/webview2"

echo "==> [1/4] Downloading Official PHP 8.4 NTS Windows x64..."
PHP_URL="https://windows.php.net/downloads/releases/php-8.4.27-1-nts-Win32-vs17-x64.zip"
curl -fSL "${PHP_URL}" -o "${DEST_DIR}/php.zip"
unzip -q -o "${DEST_DIR}/php.zip" -d "${DEST_DIR}/php"
rm -f "${DEST_DIR}/php.zip"
echo "    PHP 8.4 NTS downloaded successfully."

echo "==> [2/4] Downloading Official Caddy Server Windows x64..."
CADDY_URL="https://caddyserver.com/api/download?os=windows&arch=amd64"
curl -fSL "${CADDY_URL}" -o "${DEST_DIR}/caddy/caddy.exe"
echo "    Caddy Server downloaded successfully."

echo "==> [3/4] Downloading MariaDB Portable 11.4 LTS Windows x64..."
MARIADB_VERSION="11.4.5"
MARIADB_URL="https://archive.mariadb.org/mariadb-${MARIADB_VERSION}/winx64-packages/mariadb-${MARIADB_VERSION}-winx64.zip"
curl -fSL "${MARIADB_URL}" -o "${DEST_DIR}/mariadb.zip"
echo "    Extracting minimal MariaDB runtime (bin, share)..."
unzip -q -o "${DEST_DIR}/mariadb.zip" -d "${DEST_DIR}/mariadb_extracted"
EXTRACTED_SUBDIR=$(find "${DEST_DIR}/mariadb_extracted" -mindepth 1 -maxdepth 1 -type d | head -n 1)

mkdir -p "${DEST_DIR}/mariadb/bin"
mkdir -p "${DEST_DIR}/mariadb/share"
cp -r "${EXTRACTED_SUBDIR}/bin/mysqld.exe" "${DEST_DIR}/mariadb/bin/"
cp -r "${EXTRACTED_SUBDIR}/bin/mysqladmin.exe" "${DEST_DIR}/mariadb/bin/"
cp -r "${EXTRACTED_SUBDIR}/bin/mysql.exe" "${DEST_DIR}/mariadb/bin/"
cp -r "${EXTRACTED_SUBDIR}/bin/mysql_install_db.exe" "${DEST_DIR}/mariadb/bin/"
cp -r "${EXTRACTED_SUBDIR}/bin/"*.dll "${DEST_DIR}/mariadb/bin/" 2>/dev/null || true
cp -r "${EXTRACTED_SUBDIR}/share/english" "${DEST_DIR}/mariadb/share/" 2>/dev/null || true
cp -r "${EXTRACTED_SUBDIR}/share/charsets" "${DEST_DIR}/mariadb/share/" 2>/dev/null || true

rm -rf "${DEST_DIR}/mariadb_extracted"
rm -f "${DEST_DIR}/mariadb.zip"
echo "    MariaDB minimal runtime prepared successfully."

echo "==> [4/4] Downloading Microsoft Edge WebView2 Evergreen Bootstrapper..."
WEBVIEW2_URL="https://go.microsoft.com/fwlink/p/?LinkId=2124703"
curl -fSL "${WEBVIEW2_URL}" -o "${DEST_DIR}/webview2/MicrosoftEdgeWebview2Setup.exe"
echo "    WebView2 bootstrapper downloaded successfully."

echo "==> All Windows desktop binaries ready in: ${DEST_DIR}"
