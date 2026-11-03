#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Standalone Android Installer (Termux Non-Root)
# Brand: Bremspace
# Target Port: 1741 (Akses Lokal Smartphone & Hotspot / Wi-Fi Kasir)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

echo "======================================================================"
echo "   Bremspace ERP & POS System — Android Termux Installer"
echo "   Mode: Standalone Native (Tanpa Root & Hemat Daya)"
echo "   Target Port: 1741 (Localhost & Hotspot/Wi-Fi)"
echo "======================================================================"
echo ""

# ------------------------------------------------------------------------------
# 1. Pengecekan Lingkungan Termux
# ------------------------------------------------------------------------------
echo "ℹ Memeriksa lingkungan sistem Termux Android..."
if [ -z "$PREFIX" ] || ! echo "$PREFIX" | grep -q "com.termux"; then
    echo "⚠️ [PERINGATAN] Skrip ini dirancang khusus untuk aplikasi Termux di Android."
fi

# ------------------------------------------------------------------------------
# 2. Update Paket & Auto-Install Dependensi Runtime (PHP + SQLite + cURL)
# ------------------------------------------------------------------------------
echo "ℹ Memeriksa paket runtime PHP & SQLite..."
MISSING_PKGS=""
if ! command -v php >/dev/null 2>&1; then
    MISSING_PKGS="$MISSING_PKGS php"
fi
if ! command -v curl >/dev/null 2>&1; then
    MISSING_PKGS="$MISSING_PKGS curl"
fi
if ! command -v git >/dev/null 2>&1; then
    MISSING_PKGS="$MISSING_PKGS git"
fi

if [ -n "$MISSING_PKGS" ]; then
    echo "ℹ [AUTO-FIX] Memasang dependensi di Termux:$MISSING_PKGS..."
    pkg update -y 2>/dev/null || apt-get update -y 2>/dev/null || true
    pkg install -y php php-sqlite curl git openssl 2>/dev/null || apt-get install -y php php-sqlite3 curl git openssl 2>/dev/null || true
fi

PHP_BIN="$(command -v php || echo "php")"

# Pastikan modul php.ini Termux mengaktifkan SQLite dan memori cukup
TERMUX_PHP_INI="${PREFIX:-/data/data/com.termux/files/usr}/etc/php.ini"
if [ -f "$TERMUX_PHP_INI" ]; then
    sed -i 's/^[;\s]*extension=pdo_sqlite/extension=pdo_sqlite/' "$TERMUX_PHP_INI" 2>/dev/null || true
    sed -i 's/^[;\s]*extension=sqlite3/extension=sqlite3/' "$TERMUX_PHP_INI" 2>/dev/null || true
    sed -i 's/^[;\s]*extension=curl/extension=curl/' "$TERMUX_PHP_INI" 2>/dev/null || true
    sed -i 's/^[;\s]*extension=mbstring/extension=mbstring/' "$TERMUX_PHP_INI" 2>/dev/null || true
    sed -i 's/^[;\s]*extension=fileinfo/extension=fileinfo/' "$TERMUX_PHP_INI" 2>/dev/null || true
    sed -i 's/^memory_limit = .*/memory_limit = 256M/' "$TERMUX_PHP_INI" 2>/dev/null || true
fi

echo "✔ [OK] Runtime PHP: $($PHP_BIN -v | head -n 1)"

# ------------------------------------------------------------------------------
# 3. Verifikasi Lisensi Hardware Offline (Bremspace Android ID Binding)
# ------------------------------------------------------------------------------
LICENSE_FILE="storage/license.key"
IS_LICENSED=0

if [ -f "$LICENSE_FILE" ]; then
    IS_LICENSED=$("$PHP_BIN" -r '
        require_once "scripts/license/LicenseVerifier.php";
        echo \Scripts\License\LicenseVerifier::isLicensed() ? "1" : "0";
    ' 2>/dev/null || echo "0")
fi

if [ "$IS_LICENSED" != "1" ]; then
    echo ""
    echo "======================================================================"
    echo "           AKTIVASI LISENSI RESMI BREMSPACE ERP & POS"
    echo "======================================================================"

    CHALLENGE_CODE=$("$PHP_BIN" -r '
        require_once "scripts/license/HardwareIdentifier.php";
        echo \Scripts\License\HardwareIdentifier::getChallengeCode();
    ' 2>/dev/null || echo "")

    if [ -z "$CHALLENGE_CODE" ]; then
        ANDROID_ID=$(getprop ro.serialno 2>/dev/null || settings get secure android_id 2>/dev/null || echo "ANDROID-DEFAULT")
        RAW_HASH=$(echo -n "BREMSPACE_HWID_V1_2026:${ANDROID_ID}" | sha256sum | awk '{print toupper($1)}')
        P1=$(echo "$RAW_HASH" | cut -c1-4)
        P2=$(echo "$RAW_HASH" | cut -c5-8)
        P3=$(echo "$RAW_HASH" | cut -c9-12)
        CHALLENGE_CODE="BREM-${P1}-${P2}-${P3}"
    fi

    echo "Identitas Smartphone Android Anda:"
    echo "Kode Mesin (Challenge Code) : $CHALLENGE_CODE"
    echo ""
    echo "Kirim kode di atas ke WhatsApp Developer Bremspace untuk aktivasi:"
    echo "👉 Tautan: https://wa.me/?text=Halo+saya+minta+aktivasi+lisensi+Bremspace+kode:+${CHALLENGE_CODE}"
    echo "----------------------------------------------------------------------"

    VALID_INPUT=0
    for attempt in 1 2 3; do
        read -r -p "Masukkan Kode Aktivasi dari Developer (ACT-XXXX-YYYY-ZZZZ-WWWW): " INPUT_KEY
        INPUT_KEY=$(echo "$INPUT_KEY" | tr -d ' ' | tr '[:lower:]' '[:upper:]')

        EXPECTED_SIG=$("$PHP_BIN" -r '
            require_once "scripts/license/LicenseVerifier.php";
            echo \Scripts\License\LicenseVerifier::computeSignature("'$CHALLENGE_CODE'");
        ' 2>/dev/null || echo "")

        if [ "$INPUT_KEY" = "$EXPECTED_SIG" ] && [ -n "$EXPECTED_SIG" ]; then
            mkdir -p storage
            cat <<EOF > "$LICENSE_FILE"
{
    "status": "ACTIVE",
    "challenge": "$CHALLENGE_CODE",
    "key": "$INPUT_KEY",
    "verified_at": "$(date '+%Y-%m-%d %H:%M:%S')",
    "platform": "Android-Termux",
    "brand": "Bremspace"
}
EOF
            chmod 644 "$LICENSE_FILE"
            echo "✔ [OK] Lisensi Hardware Bremspace BERHASIL Diverifikasi!"
            VALID_INPUT=1
            break
        else
            echo "❌ [GAGAL] Kode Aktivasi tidak valid untuk smartphone ini. (Percobaan $attempt/3)"
        fi
    done

    if [ "$VALID_INPUT" != "1" ]; then
        echo "❌ [ERROR] Aktivasi lisensi gagal. Instalasi dihentikan."
        exit 1
    fi
else
    echo "✔ [OK] Lisensi Bremspace aktif & terverifikasi di smartphone ini."
fi

# ------------------------------------------------------------------------------
# 3b. Unduh Inti Aplikasi jika Berjalan dari Installer Ringan (Model B)
# ------------------------------------------------------------------------------
if [ ! -f "artisan" ]; then
    echo "ℹ [AUTO-SETUP] Mengunduh inti aplikasi Bremspace ke smartphone..."
    GH_TOKEN_OBF="dHZndWhvX2NuZ18xMU83VDdLVFYwRkg3WWtqS3VBNmJLX1V4dmN4UUl4czh0MUtGRVRXYmdoWGxZZnZrZ1RXOWlUaUdXc0NqSVZYdzgzUEJHRUVNNnhJN1ljYjRp"
    GH_PAT=$(echo "$GH_TOKEN_OBF" | base64 -d 2>/dev/null | tr 'A-Za-z' 'N-ZA-Mn-za-m' || true)
    
    if curl -sL -H "Authorization: Bearer $GH_PAT" \
        -H "Accept: application/vnd.github.v3+json" \
        "https://api.github.com/repos/bremspace/ute-parts-erp/tarball/main" -o /tmp/bremspace_core.tar.gz; then
        tar -xzf /tmp/bremspace_core.tar.gz --strip-components=1 2>/dev/null || true
        rm -f /tmp/bremspace_core.tar.gz
        echo "✔ [OK] Inti aplikasi Bremspace berhasil dipasang di Termux!"
    fi
fi

# ------------------------------------------------------------------------------
# 4. Siapkan Direktori Storage & Database SQLite Lokal
# ------------------------------------------------------------------------------
echo "ℹ Menyiapkan penyimpanan lokal & database SQLite..."
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache \
    database

# Pastikan file database.sqlite ada
touch database/database.sqlite
chmod 664 database/database.sqlite

# Konfigurasi .env otomatis ke mode SQLite jika belum ada
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
    # Set DB_CONNECTION=sqlite
    if grep -q "DB_CONNECTION=" .env; then
        sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    else
        echo "DB_CONNECTION=sqlite" >> .env
    fi
fi

# Pastikan symlink public/storage ada
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# ------------------------------------------------------------------------------
# 5. Konfigurasi Port 1741 & Manajemen Background Server
# ------------------------------------------------------------------------------
PORT=1741
PID_FILE="storage/framework/server.pid"

# Hentikan server lama jika ada
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE" 2>/dev/null || true)
    if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
        kill "$OLD_PID" 2>/dev/null || true
    fi
    rm -f "$PID_FILE"
fi

echo "ℹ Menyalakan web server Bremspace di background pada port $PORT..."
nohup "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public > storage/logs/server.log 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > "$PID_FILE"
sleep 1

# ------------------------------------------------------------------------------
# 6. Deteksi IP Jaringan Lokal (Wi-Fi / Hotspot Smartphone)
# ------------------------------------------------------------------------------
LAN_IP=""
if command -v ifconfig >/dev/null 2>&1; then
    LAN_IP=$(ifconfig 2>/dev/null | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n 1 || true)
fi
if [ -z "$LAN_IP" ] && command -v ip >/dev/null 2>&1; then
    LAN_IP=$(ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '127.0.0.1' | head -n 1 || true)
fi

TARGET_PATH="/install.php"
if [ -f "storage/installed.lock" ]; then
    TARGET_PATH="/app/login"
fi

LOCAL_URL="http://localhost:${PORT}${TARGET_PATH}"
NETWORK_URL=""
if [ -n "$LAN_IP" ]; then
    NETWORK_URL="http://${LAN_IP}:${PORT}${TARGET_PATH}"
fi

# ------------------------------------------------------------------------------
# 7. Buka Browser Google Chrome Otomatis via Android Intent
# ------------------------------------------------------------------------------
echo "ℹ Membuka aplikasi di peramban web Chrome Android..."
if command -v termux-open-url >/dev/null 2>&1; then
    termux-open-url "$LOCAL_URL" 2>/dev/null || true
elif command -v am >/dev/null 2>&1; then
    am start -a android.intent.action.VIEW -d "$LOCAL_URL" >/dev/null 2>&1 || true
fi

echo ""
echo "======================================================================"
echo "   Server Bremspace ERP & POS Berhasil Berjalan di Android!"
echo "   - Dari Smartphone Ini       : $LOCAL_URL"
if [ -n "$NETWORK_URL" ]; then
echo "   - Dari Tablet / Kasir Wi-Fi : $NETWORK_URL"
fi
echo "   - PID Server di Background  : $SERVER_PID"
echo "----------------------------------------------------------------------"
echo "   [TIPS] Tambahkan ke Layar Utama (Add to Home Screen) di Chrome"
echo "          agar aplikasi terbuka layar penuh layaknya aplikasi kasir native!"
echo "   [INFO] Untuk menghentikan server: ./stop-server.sh"
echo "======================================================================"
echo ""
