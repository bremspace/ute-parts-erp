#!/usr/bin/env bash
# ==============================================================================
# ERP & POS System — Production / Local App Launcher (Linux & macOS)
# Menjalankan seluruh service aplikasi: Web Server, Database, Queue Worker,
# Cloudflare Tunnel, dan persiapan runtime agar aplikasi siap digunakan.
# Port Default: 1741 (Akses Lokal & Jaringan Wi-Fi/LAN)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   ERP & POS System — Application Launcher"
echo "   Memulai server & mempersiapkan seluruh layanan sistem..."
echo "======================================================================"
echo ""

# 1. Deteksi Sistem Operasi & Swap Memory (Jika RAM <= 2GB)
OS_TYPE="$(uname -s)"
if [ "$OS_TYPE" = "Linux" ] && [ -f /proc/meminfo ]; then
    MEM_TOTAL_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    SWAP_TOTAL_MB=$(awk '/SwapTotal/ {print int($2/1024)}' /proc/meminfo)
    if [ "$MEM_TOTAL_MB" -gt 0 ] && [ "$MEM_TOTAL_MB" -le 2048 ] && [ "$SWAP_TOTAL_MB" -lt 1024 ]; then
        if [ ! -f /swapfile ]; then
            sudo fallocate -l 2G /swapfile 2>/dev/null || sudo dd if=/dev/zero of=/swapfile bs=1M count=2048 2>/dev/null || true
            sudo chmod 600 /swapfile 2>/dev/null || true
            sudo mkswap /swapfile 2>/dev/null || true
        fi
        sudo swapon /swapfile 2>/dev/null || true
    fi
fi

# 2. Deteksi PHP Binary (Minimal PHP 8.4)
PHP_BIN=""
CANDIDATES=(
    "/usr/bin/php8.5"
    "/usr/local/lsws/lsphp85/bin/php"
    "/usr/bin/php8.4"
    "/usr/local/lsws/lsphp84/bin/php"
    "$(which php 2>/dev/null || true)"
    "/usr/local/bin/php"
    "/opt/homebrew/bin/php"
)

for bin in "${CANDIDATES[@]}"; do
    if [ -n "$bin" ] && [ -x "$bin" ]; then
        VER_OK="$("$bin" -r 'echo version_compare(PHP_VERSION, "8.4.0", ">=") ? "1" : "0";' 2>/dev/null || echo "0")"
        if [ "$VER_OK" = "1" ]; then
            PHP_BIN="$bin"
            break
        fi
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "❌ [ERROR] PHP >= 8.4 tidak ditemukan di sistem."
    echo "Silakan install PHP 8.4+ terlebih dahulu."
    exit 1
fi

echo "✔ [OK] PHP: $PHP_BIN ($("$PHP_BIN" -v | head -n 1 | awk '{print $1,$2}')"

# 3. Persiapan Direktori Storage, Database & Hak Akses
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public database
chmod -R 775 storage bootstrap/cache database 2>/dev/null || true

# 4. Pastikan Symlink Storage Aktif
if [ ! -L "public/storage" ] && [ ! -d "public/storage" ]; then
    "$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
fi

# 5. Cek & Nyalakan Service Database (MySQL/MariaDB) jika diperlukan
DB_CONN="mysql"
if [ -f ".env" ]; then
    DB_CONN=$(grep -E "^DB_CONNECTION=" .env 2>/dev/null | cut -d= -f2- | tr -d '\"' | tr -d "'" | tr -d '\r' || echo "mysql")
fi

if [ "$DB_CONN" = "mysql" ]; then
    if ! netstat -tuln 2>/dev/null | grep -q ":3306 " && ! ss -tuln 2>/dev/null | grep -q ":3306 " && ! lsof -i :3306 >/dev/null 2>&1; then
        echo "ℹ [INFO] Mengaktifkan service MySQL lokal..."
        sudo systemctl start mysql 2>/dev/null || sudo systemctl start mariadb 2>/dev/null || sudo service mysql start 2>/dev/null || true
        sleep 1
    fi
fi

# 6. Konfigurasi Port Server (Default: 1741)
PORT=1741
SERVER_ALREADY_RUNNING=0

# Cek apakah server kita sudah aktif di port 1741
if lsof -i :"$PORT" >/dev/null 2>&1 || nc -z 127.0.0.1 "$PORT" >/dev/null 2>&1; then
    SERVER_ALREADY_RUNNING=1
fi

# 7. Nyalakan Web Server di Background jika belum aktif
if [ "$SERVER_ALREADY_RUNNING" = "0" ]; then
    echo "ℹ [INFO] Menyalakan Web Server di port $PORT..."
    nohup "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public > storage/logs/server.log 2>&1 &
    SERVER_PID=$!
    echo "$SERVER_PID" > storage/framework/server.pid
    sleep 1
    echo "✔ [OK] Web Server aktif (PID: $SERVER_PID)"
else
    SERVER_PID=$(cat storage/framework/server.pid 2>/dev/null || echo "Running")
    echo "✔ [OK] Web Server sudah aktif di port $PORT (PID: $SERVER_PID)"
fi

# 8. Nyalakan Background Queue Worker (Untuk Notifikasi WA/Email & Job Antrean)
QUEUE_RUNNING=0
if [ -f "storage/framework/queue.pid" ]; then
    Q_PID=$(cat storage/framework/queue.pid 2>/dev/null || true)
    if [ -n "$Q_PID" ] && kill -0 "$Q_PID" 2>/dev/null; then
        QUEUE_RUNNING=1
    fi
fi

if [ "$QUEUE_RUNNING" = "0" ] && [ -f ".env" ]; then
    echo "ℹ [INFO] Menyalakan Background Queue Worker..."
    nohup "$PHP_BIN" artisan queue:work --sleep=3 --tries=3 > storage/logs/queue.log 2>&1 &
    QUEUE_PID=$!
    echo "$QUEUE_PID" > storage/framework/queue.pid
    echo "✔ [OK] Queue Worker aktif (PID: $QUEUE_PID)"
else
    echo "✔ [OK] Queue Worker siap."
fi

# 9. Cek Cloudflare Tunnel jika dikonfigurasi
if [ -f ".env" ]; then
    CF_TOKEN=$(grep -E "^CLOUDFLARE_TUNNEL_TOKEN=" .env 2>/dev/null | cut -d= -f2- | tr -d '\"' | tr -d "'" | tr -d '\r' || true)
    if [ -n "$CF_TOKEN" ] && command -v cloudflared >/dev/null 2>&1; then
        if ! pgrep -x cloudflared >/dev/null 2>&1; then
            echo "ℹ [INFO] Menyalakan Cloudflare Tunnel..."
            nohup cloudflared tunnel run --token "$CF_TOKEN" > storage/logs/cloudflared.log 2>&1 &
            echo "✔ [OK] Cloudflare Tunnel aktif di background."
        else
            echo "✔ [OK] Cloudflare Tunnel sudah berjalan."
        fi
    fi
fi

# 10. Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
LAN_IP=""
if command -v hostname >/dev/null 2>&1; then
    LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
fi
if [ -z "$LAN_IP" ] && command -v ip >/dev/null 2>&1; then
    LAN_IP=$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7}')
fi
if [ -z "$LAN_IP" ] && command -v ifconfig >/dev/null 2>&1; then
    LAN_IP=$(ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n 1)
fi

# 11. Tentukan Rute Tujuan (Default: /app/login jika terpasang, /install.php jika belum)
TARGET_PATH="/app/login"
if [ ! -f "storage/installed.lock" ] && [ ! -f ".env" ]; then
    TARGET_PATH="/install.php"
fi

LOCAL_URL="http://localhost:${PORT}${TARGET_PATH}"
NETWORK_URL=""
if [ -n "$LAN_IP" ]; then
    NETWORK_URL="http://${LAN_IP}:${PORT}${TARGET_PATH}"
fi

ONLINE_URL=""
if [ -f ".env" ]; then
    PARSED_APP_URL=$(grep -E "^APP_URL=" .env 2>/dev/null | cut -d= -f2- | tr -d '\"' | tr -d "'" | tr -d '\r' || true)
    if [ -n "$PARSED_APP_URL" ] && [[ "$PARSED_APP_URL" =~ ^https?:// ]] && ! [[ "$PARSED_APP_URL" =~ localhost|127\.0\.0\.1 ]]; then
        ONLINE_URL="${PARSED_APP_URL}${TARGET_PATH}"
    fi
fi

# 12. Buka Browser Otomatis
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$LOCAL_URL" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    open "$LOCAL_URL" >/dev/null 2>&1 &
fi

echo ""
echo "======================================================================"
echo "   Aplikasi ERP & POS Berhasil Berjalan!"
echo "   - Komputer Ini (Lokal)    : $LOCAL_URL"
if [ -n "$NETWORK_URL" ]; then
echo "   - Dari HP/Tablet/Kasir LAN: $NETWORK_URL"
fi
if [ -n "$ONLINE_URL" ]; then
echo "   - Akses Internet (Online) : $ONLINE_URL"
fi
echo "----------------------------------------------------------------------"
echo "   Status Layanan:"
echo "   ✔ Web Server   : Port $PORT (Aktif)"
echo "   ✔ Antrean/Job  : Database Queue Worker (Aktif)"
echo "   ✔ Database     : $DB_CONN"
echo "----------------------------------------------------------------------"
echo "   [INFO] Server tetap berjalan di background walau terminal ini ditutup."
echo "   [INFO] Untuk menghentikan aplikasi, jalankan: ./stop-server.sh"
echo "======================================================================"
