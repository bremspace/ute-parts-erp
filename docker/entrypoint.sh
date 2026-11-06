#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Container Bootstrap Entrypoint
# ==============================================================================
set -e

cd /var/www/html

# 1. Pastikan file .env ada dan terkonfigurasi
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        cat << 'EOF' > .env
APP_NAME="Bremspace ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:1741

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=bremspace_erp
DB_USERNAME=bremspace
DB_PASSWORD=bremspace_secret

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
EOF
    fi
fi

# Pastikan APP_KEY terisi
if grep -q "^APP_KEY=$" .env 2>/dev/null || ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    php artisan key:generate --force 2>/dev/null || true
fi

# Pastikan binding challenge host tersimpan di .env untuk middleware lisensi
if [ -n "$BREMSPACE_HOST_CHALLENGE" ] && [ -f ".env" ]; then
    if ! grep -q "^BREMSPACE_HOST_CHALLENGE=" .env; then
        echo "BREMSPACE_HOST_CHALLENGE=$BREMSPACE_HOST_CHALLENGE" >> .env
    fi
fi

# 2. Sinkronkan asset public terbaru dari public_template jika volume public dimount
if [ -d "/var/www/html/public_template" ]; then
    mkdir -p public/build
    cp -ru /var/www/html/public_template/* public/ 2>/dev/null || true
fi

# 2b. Sinkronkan file database/seeders jika direktori database dimount dari host
if [ -d "/var/www/html/database_template" ]; then
    if [ ! -f "/var/www/html/database/seeders/DatabaseSeeder.php" ]; then
        mkdir -p database/seeders database/migrations database/factories
        cp -rn /var/www/html/database_template/* database/ 2>/dev/null || true
    fi
fi

# 3. Pastikan struktur direktori storage & izin tulis siap
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache \
    database

chmod -R 775 storage bootstrap/cache database 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true

# 4. Pastikan file database SQLite ada jika mode SQLite dipilih
if [ -f ".env" ] && grep -q "DB_CONNECTION=sqlite" .env; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite 2>/dev/null || true
    chown www-data:www-data database/database.sqlite 2>/dev/null || true
fi

# 5. Buat symlink storage publik jika belum ada
if [ ! -L "public/storage" ] && [ ! -d "public/storage" ]; then
    php artisan storage:link 2>/dev/null || true
fi

# 6. Auto-discover packages Laravel
php artisan package:discover --ansi 2>/dev/null || true

# 7. Jika container adalah worker antrean (queue), tunggu hingga migrasi database selesai
case "$*" in
    *"queue:work"*)
        echo "[INFO] Menunggu inisialisasi database dan tabel migrasi sebelum memulai queue:work..."
        while ! php artisan migrate:status >/dev/null 2>&1; do
            sleep 4
        done
        echo "[OK] Database dan tabel migrasi siap. Memulai queue:work..."
        ;;
esac

# 8. Jalankan perintah container utama (CMD)
exec "$@"
