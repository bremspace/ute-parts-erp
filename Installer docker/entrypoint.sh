#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
    cp .env.docker .env
fi

grep -q '^APP_KEY=' .env || echo 'APP_KEY=' >> .env
if ! grep -q '^APP_KEY=.\+' .env; then
    APP_KEY_VALUE="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

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

php artisan migrate --force

if [ ! -f storage/.installed ]; then
    php artisan db:seed --force --no-interaction || true
    touch storage/.installed
fi

php artisan storage:link || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Jalankan worker antrean & scheduler di background (container single-process)
su -s /bin/sh www-data -c "cd /var/www/html && php artisan queue:work --sleep=3 --tries=3" > /var/www/html/storage/logs/queue-worker.log 2>&1 &
su -s /bin/sh www-data -c "cd /var/www/html && php artisan schedule:work" > /var/www/html/storage/logs/scheduler.log 2>&1 &

exec "$@"