#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Container Bootstrap Entrypoint
# ==============================================================================
set -e

cd /var/www/html

# 1. Pastikan struktur direktori storage & izin tulis siap
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

# 2. Pastikan file database SQLite ada jika mode SQLite dipilih
if [ -f ".env" ] && grep -q "DB_CONNECTION=sqlite" .env; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite 2>/dev/null || true
    chown www-data:www-data database/database.sqlite 2>/dev/null || true
fi

# 3. Buat symlink storage publik jika belum ada
if [ ! -L "public/storage" ] && [ ! -d "public/storage" ]; then
    php artisan storage:link 2>/dev/null || true
fi

# 4. Auto-discover packages Laravel
php artisan package:discover --ansi 2>/dev/null || true

# 5. Jalankan perintah container utama (CMD)
exec "$@"
