#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Client Release Packager
# Menghasilkan berkas Bremspace-ERP-Release.zip yang bersih & aman untuk klien
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

RELEASE_ZIP="Bremspace-ERP-Release.zip"
TMP_DIR="/tmp/bremspace_release_build"

echo "======================================================================"
echo "   Membangun Paket Rilis Klien: $RELEASE_ZIP"
echo "======================================================================"

# 1. Bersihkan build sementara lama
rm -rf "$TMP_DIR" "$RELEASE_ZIP"
mkdir -p "$TMP_DIR"

# 2. Salin seluruh proyek ke direktori staging
echo "ℹ Menyalin berkas aplikasi..."
rsync -av --quiet ./ "$TMP_DIR/" \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='tools/license/developer-keygen.html' \
    --exclude='storage/license.key' \
    --exclude='storage/installed.lock' \
    --exclude='storage/logs/*.log' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='docker/data/mysql/*' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude="$RELEASE_ZIP"

# 3. Pastikan template .env.example tersedia sebagai acuan awal
if [ -f ".env.example" ] && [ ! -f "$TMP_DIR/.env" ]; then
    cp .env.example "$TMP_DIR/.env.example"
fi

# 4. Pastikan direktori kosong yang diperlukan tetap ada
mkdir -p "$TMP_DIR/storage/framework/cache/data"
mkdir -p "$TMP_DIR/storage/framework/sessions"
mkdir -p "$TMP_DIR/storage/framework/views"
mkdir -p "$TMP_DIR/storage/logs"
mkdir -p "$TMP_DIR/storage/app/public"
mkdir -p "$TMP_DIR/bootstrap/cache"
mkdir -p "$TMP_DIR/database"
mkdir -p "$TMP_DIR/docker/data/mysql"

# 5. Buat berkas ZIP menggunakan zip command
echo "ℹ Mengompresi menjadi $RELEASE_ZIP..."
cd "$TMP_DIR"
zip -r -q "$PROJECT_ROOT/$RELEASE_ZIP" .

# 6. Bersihkan direktori staging
rm -rf "$TMP_DIR"
cd "$PROJECT_ROOT"

ZIP_SIZE=$(du -h "$RELEASE_ZIP" | awk '{print $1}')
echo ""
echo "======================================================================"
echo "✔ [SUKSES] Paket rilis klien berhasil dibuat!"
echo "   Nama Berkas : $RELEASE_ZIP"
echo "   Ukuran      : $ZIP_SIZE"
echo "   Lokasi      : $PROJECT_ROOT/$RELEASE_ZIP"
echo "----------------------------------------------------------------------"
echo "   Berkas $RELEASE_ZIP ini yang Anda kirimkan ke Klien."
echo "   (File developer-keygen.html dan .git otomatis dikecualikan/aman)."
echo "======================================================================"
echo ""
