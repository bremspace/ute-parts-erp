#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Standalone macOS Packager (.app / .dmg ready)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")"
cd "$PROJECT_ROOT"

DIST_DIR="$PROJECT_ROOT/dist/macos"
BUILD_TMP="/tmp/bremspace_build_macos"
APP_BUNDLE="$BUILD_TMP/BremspaceERP.app"
RELEASE_TAR="$DIST_DIR/BremspaceERP-macOS-Universal.tar.gz"

echo "======================================================================"
echo "   Membangun Paket Standalone macOS: $RELEASE_TAR"
echo "======================================================================"

rm -rf "$BUILD_TMP" "$RELEASE_TAR"
mkdir -p "$APP_BUNDLE/Contents/MacOS" \
         "$APP_BUNDLE/Contents/Resources/app" \
         "$APP_BUNDLE/Contents/Resources/config" \
         "$DIST_DIR"

# 1. Salin aplikasi
echo "ℹ Menyalin berkas aplikasi ke bundle .app..."
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
         "$PROJECT_ROOT/" "$APP_BUNDLE/Contents/Resources/app/"

# 2. Jalankan Proteksi Bytecode / Enkripsi Source Code
echo "ℹ Menjalankan proteksi bytecode & anti-tamper..."
php "$PROJECT_ROOT/tools/security/protect-code.php" "$APP_BUNDLE/Contents/Resources/app"

# 3. Salin launcher executable & config
cp "$PROJECT_ROOT/tools/macos/launcher/bremspace-launcher.sh" "$APP_BUNDLE/Contents/MacOS/bremspace-launcher"
chmod +x "$APP_BUNDLE/Contents/MacOS/bremspace-launcher"

cp "$PROJECT_ROOT/tools/macos/config/Caddyfile.macos" "$APP_BUNDLE/Contents/Resources/config/"

# 4. Info.plist metadata
cat << 'PLIST' > "$APP_BUNDLE/Contents/Info.plist"
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>bremspace-launcher</string>
    <key>CFBundleIdentifier</key>
    <string>id.bremspace.erp.desktop</string>
    <key>CFBundleName</key>
    <string>Bremspace ERP</string>
    <key>CFBundleDisplayName</key>
    <string>Bremspace ERP &amp; POS</string>
    <key>CFBundleVersion</key>
    <string>1.0.0</string>
    <key>CFBundleShortVersionString</key>
    <string>1.0.0</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>LSMinimumSystemVersion</key>
    <string>12.0</string>
    <key>NSHighResolutionCapable</key>
    <true/>
</dict>
</plist>
PLIST

# 5. Kompresi ke archive
echo "ℹ Mengompresi arsip rilis..."
tar -czf "$RELEASE_TAR" -C "$BUILD_TMP" BremspaceERP.app

rm -rf "$BUILD_TMP"

echo "======================================================================"
echo "✓ Paket rilis macOS selesai dibuat:"
ls -lh "$RELEASE_TAR"
echo "======================================================================"
