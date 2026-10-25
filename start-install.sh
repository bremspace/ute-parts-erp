#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP & Marketplace — One-Click Universal Launcher (Linux & macOS)
# Mendukung auto-fix seluruh dependensi, folder, dan ekstensi server otomatis
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   Ute Parts ERP & Marketplace — One-Click Universal Launcher"
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
    echo "ℹ PHP belum terpasang. Mencoba instalasi PHP 8.4 otomatis..."
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update -y && sudo apt-get install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-gd php8.4-mbstring php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-imagick unzip || true
        PHP_BIN="$(which php || echo /usr/bin/php8.4)"
    fi
fi

if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
    echo "❌ [ERROR] PHP tidak ditemukan di sistem!"
    echo "Silakan install PHP >= 8.3 (Ubuntu: sudo apt install php8.4, macOS: brew install php)."
    exit 1
fi

echo "✔ [OK] PHP terdeteksi: $PHP_BIN ($("$PHP_BIN" -v | head -n 1))"

# 2. Siapkan direktori storage & bootstrap/cache
echo "ℹ [AUTO-FIX] Menyiapkan direktori storage & izin tulis..."
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public
chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 3. Deteksi & siapkan Composer
COMPOSER_CMD=""
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="composer"
elif [ -f "./composer.phar" ]; then
    COMPOSER_CMD="$PHP_BIN ./composer.phar"
else
    echo "ℹ [AUTO-FIX] Mengunduh composer.phar otomatis..."
    if command -v curl >/dev/null 2>&1; then
        curl -sS -L https://getcomposer.org/composer-stable.phar -o ./composer.phar || true
    fi
    if [ ! -f "./composer.phar" ] && command -v curl >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir=. --filename=composer.phar || true
    fi
    if [ -f "./composer.phar" ]; then
        chmod +x ./composer.phar
        COMPOSER_CMD="$PHP_BIN ./composer.phar"
    fi
fi

# 4. Instalasi dependensi vendor/ jika belum ada
if [ ! -f "vendor/autoload.php" ]; then
    if [ -n "$COMPOSER_CMD" ]; then
        echo "ℹ [AUTO-FIX] Menjalankan composer install untuk melengkapi vendor..."
        $COMPOSER_CMD install --no-interaction --prefer-dist --optimize-autoloader || true
    else
        echo "⚠️ [WARN] Composer belum tersedia. Dependensi dapat dilengkapi via GUI Installer."
    fi
fi

# 5. Cari port kosong (8000..8020)
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

# 6. Buka Web Browser Otomatis
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    open "$URL" >/dev/null 2>&1 &
fi

# 7. Jalankan Built-in Web Server
echo "======================================================================"
echo "   Server aktif di: http://localhost:${PORT}"
echo "   Tekan Ctrl+C untuk menghentikan server."
echo "======================================================================"
echo ""
exec "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public
