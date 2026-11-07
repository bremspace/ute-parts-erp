#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Standalone Linux Packager (.tar.gz / AppImage ready)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")"
cd "$PROJECT_ROOT"

DIST_DIR="$PROJECT_ROOT/dist/linux"
BUILD_TMP="/tmp/bremspace_build_linux"
RELEASE_TAR="$DIST_DIR/BremspaceERP-Linux-x64.tar.gz"

echo "======================================================================"
echo "   Membangun Paket Standalone Linux: $RELEASE_TAR"
echo "======================================================================"

rm -rf "$BUILD_TMP" "$RELEASE_TAR"
mkdir -p "$BUILD_TMP/app" "$BUILD_TMP/bin" "$BUILD_TMP/config" "$DIST_DIR"

# 1. Salin aplikasi
echo "ℹ Menyalin berkas aplikasi..."
rsync -a --exclude='.git' \
         --exclude='node_modules' \
         --exclude='tests' \
         --exclude='tools' \
         --exclude='dist' \
         --exclude='.env*' \
         --exclude='*.key' \
         --exclude='installed.lock' \
         --exclude='storage/logs/*.log' \
         --exclude='storage/framework/cache/data/*' \
         --exclude='storage/framework/sessions/*' \
         --exclude='storage/framework/views/*' \
         --exclude='*.log' \
         "$PROJECT_ROOT/" "$BUILD_TMP/app/"

# 2. Jalankan Proteksi Bytecode / Enkripsi Source Code
echo "ℹ Menjalankan proteksi bytecode & anti-tamper..."
php "$PROJECT_ROOT/tools/security/protect-code.php" "$BUILD_TMP/app"

# 3. Salin launcher dan konfigurasi
cp "$PROJECT_ROOT/tools/linux/launcher/bremspace-erp.sh" "$BUILD_TMP/bin/"
chmod +x "$BUILD_TMP/bin/bremspace-erp.sh"

cp "$PROJECT_ROOT/tools/linux/config/Caddyfile.linux" "$BUILD_TMP/config/"
cp "$PROJECT_ROOT/tools/linux/config/bremspace-erp.service" "$BUILD_TMP/config/"

# 4. Buat archive tar.gz
echo "ℹ Mengompresi arsip rilis..."
tar -czf "$RELEASE_TAR" -C "$BUILD_TMP" .

rm -rf "$BUILD_TMP"

echo "======================================================================"
echo "✓ Paket rilis Linux selesai dibuat:"
ls -lh "$RELEASE_TAR"
echo "======================================================================"
