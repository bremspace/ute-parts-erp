#!/usr/bin/env bash
# ==============================================================================
# Script: download-binaries.sh
# Purpose: Download Windows x64 portable runtimes (FrankPHP, MariaDB, WebView2)
# ==============================================================================

set -euo pipefail

DEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/binaries"
mkdir -p "${DEST_DIR}/frankphp"
mkdir -p "${DEST_DIR}/mariadb"
mkdir -p "${DEST_DIR}/webview2"

echo "==> [1/3] Downloading FrankPHP Windows x64..."
FRANKPHP_URL="https://github.com/dunglas/frankphp/releases/latest/download/frankphp-windows-x86_64.zip"
curl -fSL "${FRANKPHP_URL}" -o "${DEST_DIR}/frankphp.zip"
unzip -q -o "${DEST_DIR}/frankphp.zip" -d "${DEST_DIR}/frankphp"
rm -f "${DEST_DIR}/frankphp.zip"
echo "    FrankPHP downloaded successfully."

echo "==> [2/3] Downloading MariaDB Portable 11.4 LTS Windows x64..."
MARIADB_VERSION="11.4.5"
MARIADB_URL="https://archive.mariadb.org/mariadb-${MARIADB_VERSION}/winx64-packages/mariadb-${MARIADB_VERSION}-winx64.zip"
curl -fSL "${MARIADB_URL}" -o "${DEST_DIR}/mariadb.zip"
echo "    Extracting minimal MariaDB runtime (bin, share)..."
unzip -q -o "${DEST_DIR}/mariadb.zip" -d "${DEST_DIR}/mariadb_extracted"
EXTRACTED_SUBDIR=$(find "${DEST_DIR}/mariadb_extracted" -mindepth 1 -maxdepth 1 -type d | head -n 1)

# Keep only essential binaries to keep installer size small (<100MB)
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

echo "==> [3/3] Downloading Microsoft Edge WebView2 Evergreen Bootstrapper..."
WEBVIEW2_URL="https://go.microsoft.com/fwlink/p/?LinkId=2124703"
curl -fSL "${WEBVIEW2_URL}" -o "${DEST_DIR}/webview2/MicrosoftEdgeWebview2Setup.exe"
echo "    WebView2 bootstrapper downloaded successfully."

echo "==> All Windows desktop binaries ready in: ${DEST_DIR}"
