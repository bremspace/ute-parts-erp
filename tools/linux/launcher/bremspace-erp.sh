#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Standalone Linux Supervisor & Launcher
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(dirname "$SCRIPT_DIR")"
APP_DIR="$BASE_DIR/app"

DATA_DIR="${XDG_DATA_HOME:-$HOME/.local/share}/bremspace-erp/data"
CONFIG_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/bremspace-erp"
STORAGE_DIR="$DATA_DIR/storage"

mkdir -p "$DATA_DIR" "$CONFIG_DIR" "$STORAGE_DIR/framework/views" "$STORAGE_DIR/framework/sessions" "$STORAGE_DIR/framework/cache" "$STORAGE_DIR/logs"

export APP_DIR
export APP_PORT="${APP_PORT:-1741}"
export CGI_PORT="${CGI_PORT:-9000}"
export BREMSPACE_LICENSE_PATH="$CONFIG_DIR/license.key"

echo "======================================================================"
echo "   Bremspace ERP & POS System — Linux Standalone Engine"
echo "======================================================================"

# 1. Deteksi IP Jaringan Lokal (Multi-Device)
LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1")
echo "ℹ Localhost URL      : http://127.0.0.1:${APP_PORT}/app"
echo "ℹ Multi-Device LAN URL: http://${LAN_IP}:${APP_PORT}/app"

# 2. Hardware License Guard
if [ -f "$STORAGE_DIR/installed.lock" ]; then
    echo "ℹ Memeriksa lisensi hardware mesin Linux..."
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
            echo "❌ Sistem Terkunci: Lisensi hardware tidak cocok untuk perangkat ini!"
            echo "   Challenge Code Mesin: $CHALLENGE"
            echo "   Hubungi WhatsApp Developer Bremspace untuk kode aktivasi."
            exit 1
        fi
        echo "✓ Lisensi hardware valid."
    fi
fi

# 3. Jalankan Web Server Caddy
CADDY_BIN=$(which caddy || echo "$BASE_DIR/bin/caddy")
CADDYFILE="$CONFIG_DIR/Caddyfile"
if [ ! -f "$CADDYFILE" ]; then
    cp "$BASE_DIR/config/Caddyfile.linux" "$CADDYFILE" 2>/dev/null || true
fi

# Jalankan Caddy di background
if command -v caddy &>/dev/null; then
    caddy run --config "$CADDYFILE" --adapter caddyfile &
    CADDY_PID=$!
    echo "✓ Caddy web server aktif (PID: $CADDY_PID)"
fi

# 4. Buka Browser
if command -v xdg-open &>/dev/null; then
    sleep 1
    xdg-open "http://127.0.0.1:${APP_PORT}/app" &>/dev/null &
fi

echo "✓ Bremspace ERP sedang berjalan. Tekan Ctrl+C untuk berhenti."
wait
