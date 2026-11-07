#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Automated VPS Setup & Installation Script
# Target: Ubuntu 22.04 / 24.04 LTS, Debian 12 (Standalone Linux / VPS)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
cd "$ROOT_DIR"

echo "======================================================================"
echo "   Bremspace ERP & POS — Automated VPS Setup"
echo "   Directory: $ROOT_DIR"
echo "======================================================================"

# 1. Deteksi PHP & Composer
PHP_BIN=$(which php || echo "/usr/bin/php")
if ! command -v php &>/dev/null; then
    echo "❌ PHP belum terinstall di VPS. Install PHP 8.4 minimal (cli, fpm, mysql, mbstring, xml, curl, zip)."
    exit 1
fi

PHP_VER=$($PHP_BIN -r 'echo PHP_VERSION;')
echo "✓ PHP terdeteksi: $PHP_VER ($PHP_BIN)"

if ! command -v composer &>/dev/null; then
    echo "❌ Composer belum terinstall di VPS. Silakan install Composer terlebih dahulu."
    exit 1
fi
echo "✓ Composer terdeteksi."

if ! command -v npm &>/dev/null; then
    echo "❌ Node.js / NPM belum terinstall di VPS. Silakan install Node.js (v20+)."
    exit 1
fi
echo "✓ Node.js & NPM terdeteksi."

# 2. Siapkan file .env
if [ ! -f ".env" ]; then
    echo "ℹ Menyalin .env.example -> .env..."
    cp .env.example .env
fi

# 3. Install Dependensi Composer
echo "ℹ Menginstall PHP dependencies (Composer)..."
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# 4. Generate App Key & Storage Link
echo "ℹ Mempersiapkan App Key & Storage Link..."
$PHP_BIN artisan key:generate --force
$PHP_BIN artisan storage:link || true

# 5. Build Aset Frontend
echo "ℹ Mengompilasi frontend assets (Vite / Tailwind)..."
npm ci --ignore-scripts || npm install --ignore-scripts
npm run build

# 6. Database Migration & Seed
read -p "Apakah Anda ingin menjalankan migrasi database & seeding sekarang? (y/n) [y]: " RUN_MIGRATE
RUN_MIGRATE=${RUN_MIGRATE:-y}
if [ "$RUN_MIGRATE" = "y" ] || [ "$RUN_MIGRATE" = "Y" ]; then
    echo "ℹ Menjalankan migrasi database..."
    $PHP_BIN artisan migrate --force
    $PHP_BIN artisan db:seed --force || true
fi

# 7. Optimasi Cache Produksi
echo "ℹ Mengoptimalkan cache konfigurasi dan rute..."
$PHP_BIN artisan config:cache || true
$PHP_BIN artisan route:cache || true
$PHP_BIN artisan view:cache || true

# 8. Set Permissions
WEB_USER="${WEB_USER:-www-data}"
echo "ℹ Mengatur hak akses folder storage & cache untuk user '$WEB_USER'..."
if id "$WEB_USER" &>/dev/null; then
    chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache public/build 2>/dev/null || true
fi
chmod -R 775 storage bootstrap/cache

# 9. Dapatkan IP VPS
IP_VPS=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1")

echo "======================================================================"
echo "🎉 Instalasi Bremspace ERP Selesai!"
echo "======================================================================"
echo "Akses Web App:"
echo "• URL Dashboard: http://${IP_VPS}:8000/app"
echo "• URL Konsumen : http://${IP_VPS}:8000/"
echo ""
echo "Jalankan server darurat/test:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
echo "Atau jalankan daemon antrean (queue worker):"
echo "  php artisan queue:work --sleep=3 --tries=3 &"
echo "======================================================================"
