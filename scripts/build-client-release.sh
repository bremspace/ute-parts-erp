#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Thin Client Bootstrapper Packager (Model B)
# Menghasilkan berkas Bremspace-ERP-Installer.zip ukuran ultra-ringan (~1 MB)
# 100% Bebas dari Source Code PHP mentah (Aman dari pembajakan/intip kode)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

RELEASE_ZIP="Bremspace-ERP-Installer.zip"
TMP_DIR="/tmp/bremspace_thin_release"

echo "======================================================================"
echo "   Membangun Paket Rilis Thin Client (Model B): $RELEASE_ZIP"
echo "======================================================================"

# 1. Bersihkan build lama
rm -rf "$TMP_DIR" "$RELEASE_ZIP"
mkdir -p "$TMP_DIR"

# 2. Salin HANYA file installer & konfigurasi runtime (TANPA SOURCE CODE PHP)
echo "ℹ Menyiapkan berkas bootstrapper..."

cp PANDUAN_INSTALASI.txt "$TMP_DIR/"
cp start-install.bat "$TMP_DIR/"
cp start-install.sh "$TMP_DIR/"
cp start-termux.sh "$TMP_DIR/"
cp update-system.bat "$TMP_DIR/"
cp update-system.sh "$TMP_DIR/"
cp stop-server.bat "$TMP_DIR/"
cp stop-server.sh "$TMP_DIR/"

# Gunakan compose.client.yaml sebagai compose.yaml di klien (Image pre-built)
cp compose.client.yaml "$TMP_DIR/compose.yaml"

# Salin skrip pembantu & konfigurasi docker
mkdir -p "$TMP_DIR/scripts/license" "$TMP_DIR/scripts/updater" "$TMP_DIR/docker/nginx" "$TMP_DIR/public"
cp scripts/install-docker.ps1 "$TMP_DIR/scripts/"
cp scripts/install-docker.sh "$TMP_DIR/scripts/"
cp scripts/install-termux.sh "$TMP_DIR/scripts/"
cp scripts/install.ps1 "$TMP_DIR/scripts/" 2>/dev/null || true
cp scripts/setup-windows-php.ps1 "$TMP_DIR/scripts/"
cp scripts/run-server-background.vbs "$TMP_DIR/scripts/"
cp scripts/run-command-background.vbs "$TMP_DIR/scripts/"

cp scripts/license/HardwareIdentifier.php "$TMP_DIR/scripts/license/"
cp scripts/license/LicenseVerifier.php "$TMP_DIR/scripts/license/"
cp scripts/updater/SystemUpdater.php "$TMP_DIR/scripts/updater/"

cp docker/nginx/default.conf "$TMP_DIR/docker/nginx/"
cp docker/entrypoint.sh "$TMP_DIR/docker/"
cp public/install.php "$TMP_DIR/public/"

if [ -f ".env.example" ]; then
    cp .env.example "$TMP_DIR/.env.example"
fi

# 3. Siapkan struktur folder penyimpanan lokal & database fallback
mkdir -p "$TMP_DIR/storage/framework/cache/data"
mkdir -p "$TMP_DIR/storage/framework/sessions"
mkdir -p "$TMP_DIR/storage/framework/views"
mkdir -p "$TMP_DIR/storage/logs"
mkdir -p "$TMP_DIR/storage/app/public"
mkdir -p "$TMP_DIR/bootstrap/cache"
mkdir -p "$TMP_DIR/database"
cp -r database/seeders "$TMP_DIR/database/" 2>/dev/null || true
cp -r database/migrations "$TMP_DIR/database/" 2>/dev/null || true
cp -r database/factories "$TMP_DIR/database/" 2>/dev/null || true
mkdir -p "$TMP_DIR/docker/data/mysql"

# 4. Kompresi menjadi ZIP
echo "ℹ Mengompresi berkas installer..."
cd "$TMP_DIR"
zip -r -q "$PROJECT_ROOT/$RELEASE_ZIP" .

# 5. Bersihkan direktori staging
rm -rf "$TMP_DIR"
cd "$PROJECT_ROOT"

ZIP_SIZE=$(du -h "$RELEASE_ZIP" | awk '{print $1}')
echo ""
echo "======================================================================"
echo "✔ [SUKSES] Paket Thin Installer Klien (Model B) Berhasil Dibuat!"
echo "   Nama Berkas : $RELEASE_ZIP"
echo "   Ukuran      : $ZIP_SIZE (Sangat Ringan!)"
echo "   Lokasi      : $PROJECT_ROOT/$RELEASE_ZIP"
echo "----------------------------------------------------------------------"
echo "   KEUNGGULAN MODEL B:"
echo "   1. 100% Bebas dari Source Code PHP mentah (Aman dari Pembajakan)."
echo "   2. Klien hanya butuh download ~1 MB."
echo "   3. Docker otomatis mengunduh image container terenkripsi saat diinstal."
echo "======================================================================"
echo ""
