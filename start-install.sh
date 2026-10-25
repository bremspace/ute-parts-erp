#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP & Marketplace — One-Click Local Launcher (Linux & macOS)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   Ute Parts ERP & Marketplace — One-Click Local Launcher"
echo "======================================================================"
echo ""

# 1. Deteksi PHP Binary
PHP_BIN=""
CANDIDATES=(
    "$(which php 2>/dev/null || true)"
    "/usr/bin/php8.4"
    "/usr/bin/php8.3"
    "/usr/local/bin/php"
    "/opt/homebrew/bin/php"
    "/usr/local/lsws/lsphp85/bin/php"
    "/usr/local/lsws/lsphp84/bin/php"
)

for bin in "${CANDIDATES[@]}"; do
    if [ -n "$bin" ] && [ -x "$bin" ]; then
        PHP_BIN="$bin"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "❌ [ERROR] PHP tidak ditemukan di sistem!"
    echo "Silakan install PHP >= 8.3 (Ubuntu: apt install php8.4, macOS: brew install php)."
    exit 1
fi

echo "✔ [OK] PHP terdeteksi: $PHP_BIN ($("$PHP_BIN" -v | head -n 1))"

# 2. Cek dependensi vendor/autoload.php
if [ ! -f "vendor/autoload.php" ]; then
    echo "ℹ [INFO] Folder vendor belum lengkap. Memeriksa Composer..."
    if command -v composer >/dev/null 2>&1; then
        echo "ℹ [INFO] Menjalankan composer install..."
        composer install --no-interaction --prefer-dist --optimize-autoloader || true
    else
        echo "⚠️ [WARN] Composer tidak ditemukan di PATH. Penginstalan dependensi dapat diselesaikan via GUI."
    fi
fi

# 3. Cari port kosong (8000..8020)
PORT=8000
while lsof -i :"$PORT" >/dev/null 2>&1 || nc -z 127.0.0.1 "$PORT" >/dev/null 2>&1; do
    PORT=$((PORT + 1))
    if [ "$PORT" -gt 8020 ]; then
        echo "❌ [ERROR] Port 8000-8020 sedang terpakai."
        exit 1
    fi
done

URL="http://localhost:${PORT}/install.php"
echo "✔ [OK] Port terpilih: $PORT"
echo "ℹ [INFO] Membuka browser ke $URL ..."
echo ""

# 4. Buka Web Browser Otomatis (Linux / macOS)
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    open "$URL" >/dev/null 2>&1 &
fi

# 5. Jalankan Built-in Web Server
echo "======================================================================"
echo "   Server aktif di: http://localhost:${PORT}"
echo "   Tekan Ctrl+C untuk menghentikan server."
echo "======================================================================"
echo ""
exec "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public
