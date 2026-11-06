#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Standalone macOS App Launcher
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_BUNDLE="$(dirname "$(dirname "$SCRIPT_DIR")")"
APP_DIR="$APP_BUNDLE/Contents/Resources/app"

DATA_DIR="$HOME/Library/Application Support/BremspaceERP/data"
CONFIG_DIR="$HOME/Library/Application Support/BremspaceERP/config"
STORAGE_DIR="$DATA_DIR/storage"

mkdir -p "$DATA_DIR" "$CONFIG_DIR" "$STORAGE_DIR/framework/views" "$STORAGE_DIR/framework/sessions" "$STORAGE_DIR/framework/cache" "$STORAGE_DIR/logs"

export APP_DIR
export APP_PORT="${APP_PORT:-1741}"
export CGI_PORT="${CGI_PORT:-9000}"
export BREMSPACE_LICENSE_PATH="$CONFIG_DIR/license.key"

echo "======================================================================"
echo "   Bremspace ERP & POS System — macOS Desktop Engine"
echo "======================================================================"

# 1. Deteksi IP Jaringan Lokal (Multi-Device)
LAN_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "127.0.0.1")
echo "ℹ Localhost URL      : http://127.0.0.1:${APP_PORT}/app"
echo "ℹ Multi-Device LAN URL: http://${LAN_IP}:${APP_PORT}/app"

# 2. Hardware License Guard (Darwin IOPlatformUUID)
if [ -f "$STORAGE_DIR/installed.lock" ]; then
    echo "ℹ Memeriksa lisensi hardware mesin macOS..."
    PHP_BIN=$(which php || echo "/usr/bin/php")
    if [ -x "$PHP_BIN" ]; then
        LIC_CHECK=$($PHP_BIN -r '
            require_once "'$APP_DIR'/scripts/license/HardwareIdentifier.php";
            require_once "'$APP_DIR'/scripts/license/LicenseVerifier.php";
            echo \Scripts\License\LicenseVerifier::isLicensed() ? "VALID" : "INVALID";
        ' 2>/dev/null || echo "ERROR")

        if [ "$LIC_CHECK" != "VALID" ]; then
            CHALLENGE=$($PHP_BIN -r '
                require_once "'$APP_DIR'/scripts/license/HardwareIdentifier.php";
                echo \Scripts\License\HardwareIdentifier::getChallengeCode();
            ' 2>/dev/null || echo "UNKNOWN")
            osascript -e 'display alert "Bremspace ERP - Lisensi Terkunci" message "Identitas hardware Mac tidak cocok dengan lisensi terdaftar.\nChallenge Code: '$CHALLENGE'\nSilakan hubungi WhatsApp Developer untuk aktivasi." as critical' 2>/dev/null || true
            exit 1
        fi
        echo "✓ Lisensi hardware valid."
    fi
fi

# 3. Jalankan Web Server Caddy
CADDYFILE="$CONFIG_DIR/Caddyfile"
if [ ! -f "$CADDYFILE" ]; then
    cp "$APP_BUNDLE/Contents/Resources/config/Caddyfile.macos" "$CADDYFILE" 2>/dev/null || true
fi

if command -v caddy &>/dev/null; then
    caddy run --config "$CADDYFILE" --adapter caddyfile &
    CADDY_PID=$!
    echo "✓ Caddy web server aktif (PID: $CADDY_PID)"
fi

# 4. Buka di Browser Mac / Kiosk
sleep 1
open "http://127.0.0.1:${APP_PORT}/app"

wait
