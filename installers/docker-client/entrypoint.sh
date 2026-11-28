#!/bin/sh
# ==============================================================================
# UTE PARTS ERP - Entry point NODE CLIENT (primary_store)
# ==============================================================================
# ATURAN INSTALLER INI:
#   - TIDAK PERNAH menjalankan seeder apa pun (client MAUPUN cloud).
#   - Data awal toko ditarik dari cloud via `php artisan sync:bootstrap`.
#   - Yang dibuat lokal hanya: migrate + SATU user admin dari
#     ADMIN_EMAIL / ADMIN_PASSWORD (lihat bootstrap-client-admin.php).
# ==============================================================================
set -e
cd /var/www/html

# ------------------------------------------------------------------------------
# 1. Persistensi .env di volume storage (disymlink), seperti installer lama.
#    Tulisan UI Pengaturan (APP_URL, APP_KEY, token deploy) tidak hilang saat
#    image dibangun ulang. env_file .env.client (host) tetap menjadi sumber
#    environment variable process dan selalu menang atas nilai di .env.
# ------------------------------------------------------------------------------
if [ -f storage/.env ]; then
    rm -f .env
    ln -s storage/.env .env
else
    [ -f .env ] || cp .env.client.example .env
    cp .env storage/.env
    rm -f .env
    ln -s storage/.env .env
fi

chown www-data:www-data storage/.env 2>/dev/null || true
chmod 664 storage/.env 2>/dev/null || true

# APP_KEY unik per node, dibuat sekali dan dipertahankan di volume storage.
grep -q '^APP_KEY=' .env || echo 'APP_KEY=' >> .env
if ! grep -q '^APP_KEY=.\+' .env; then
    APP_KEY_VALUE="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    # Jangan `sed -i`: ia mengganti symlink .env dengan berkas baru sehingga
    # storage/.env tertinggal dan APP_KEY berubah tiap restart.
    sed "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env > .env.tmp
    cat .env.tmp > .env
    rm -f .env.tmp
fi

# PENTING: env_file dari compose bisa mengirim APP_KEY kosong. Laravel Dotenv
# bersifat immutable, jadi nilai process env menang atas .env. Ekspor kunci
# final ke environment sebelum config:cache dan Apache dijalankan.
APP_KEY_VALUE="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
export APP_KEY="$APP_KEY_VALUE"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${SYNC_CLIENT_MODE:-false}" = "true" ]; then
    echo "[INFO] SYNC_CLIENT_MODE=true -> node CLIENT. Seeder dinonaktifkan permanen."
else
    echo "[INFO] SYNC_CLIENT_MODE=false -> node CLOUD. Installer ini juga TIDAK menjalankan seeder."
fi

# ------------------------------------------------------------------------------
# 2. Tunggu MySQL siap (maks. ~120 detik)
# ------------------------------------------------------------------------------
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database at $DB_HOST..."
    i=0
    until php -r "try{\$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok';}catch(Exception \$e){exit(1);}" >/dev/null 2>&1; do
        i=$((i+1))
        if [ "$i" -ge 60 ]; then
            echo "Database not ready"
            exit 1
        fi
        sleep 2
    done
fi

# ------------------------------------------------------------------------------
# 3. Migrasi struktur, lalu SATU admin lokal. TANPA seeder.
# ------------------------------------------------------------------------------
php artisan migrate --force
# Self-healing: kondisi client lama bisa punya migrasi "ran" tanpa tabel sync.
php artisan sync:ensure-tables

if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php /usr/local/bin/bootstrap-client-admin.php
else
    echo "[WARN] ADMIN_EMAIL/ADMIN_PASSWORD belum diisi; admin lokal tidak dibuat."
fi

# Bootstrap awal dari cloud: sekali saja, saat belum ada penanda seed.
# Tidak dijalankan kalau kredensial sync belum lengkap, supaya container tetap hidup.
if [ ! -f storage/.synced_from_cloud ] \
   && [ "${SYNC_ENABLED:-false}" = "true" ] \
   && [ -n "${SYNC_REMOTE_URL:-}" ] \
   && [ -n "${SYNC_API_SECRET:-}" ]; then
    echo "[INFO] Bootstrap awal dari cloud (sync:bootstrap --force)..."
    if php artisan sync:bootstrap --force; then
        echo "[INFO] Bootstrap awal selesai."
    else
        echo "[WARN] Bootstrap awal gagal. Jalankan nanti: php artisan sync:bootstrap --force"
    fi
fi

php artisan storage:link || true

# ------------------------------------------------------------------------------
# 4. Cache. Bila env_file .env.client berubah, checksum berubah -> cache lama
#    dibuang dulu supaya konfigurasi sync/DB tidak tersimpan versi lama.
# ------------------------------------------------------------------------------
ENV_SIGNATURE=$(printf '%s|' \
    "${APP_ENV:-}" "${APP_URL:-}" "${DB_HOST:-}" "${DB_PORT:-}" \
    "${DB_DATABASE:-}" "${DB_USERNAME:-}" "${DB_PASSWORD:-}" \
    "${SYNC_ENABLED:-}" "${SYNC_NODE_ROLE:-}" "${SYNC_NODE_ID:-}" \
    "${SYNC_REMOTE_URL:-}" "${SYNC_API_SECRET:-}" "${SYNC_CLIENT_MODE:-}" \
    "${QUEUE_CONNECTION:-}" "${CACHE_STORE:-}" "${SESSION_DRIVER:-}" \
    | sha256sum | awk '{print $1}')

SIG_FILE=storage/.client-env.sha256
if [ ! -f "$SIG_FILE" ] || [ "$(cat "$SIG_FILE")" != "$ENV_SIGNATURE" ]; then
    echo "[INFO] Konfigurasi berubah - membersihkan cache lama..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    printf '%s' "$ENV_SIGNATURE" > "$SIG_FILE"
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# ------------------------------------------------------------------------------
# 5. Worker antrean & scheduler di background (container single-process).
# ------------------------------------------------------------------------------
su -s /bin/sh www-data -c "cd /var/www/html && php artisan queue:work --queue=default,sync --sleep=3 --tries=3" > /var/www/html/storage/logs/queue-worker.log 2>&1 &
su -s /bin/sh www-data -c "cd /var/www/html && php artisan schedule:work" > /var/www/html/storage/logs/scheduler.log 2>&1 &

exec "$@"
