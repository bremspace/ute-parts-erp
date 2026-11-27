#!/usr/bin/env bash
# Deploy/update UTEParts dari main. Dipasang oleh install.sh.
set -Eeuo pipefail

APP_DIR="{{APP_DIR}}"
PHP_BIN="{{PHP_BIN}}"
PHP_FPM_SERVICE="{{PHP_FPM_SERVICE}}"

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }

cd "$APP_DIR"

if [[ -n "$(git status --porcelain)" ]]; then
    log "Working tree tidak bersih. Perubahan lokal akan DIBUANG."
    sleep 3
fi

log "Tarik kode terbaru..."
git fetch --all --prune
git reset --hard origin/main

log "Install dependency PHP..."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

log "Migrasi database..."
"$PHP_BIN" artisan migrate --force

log "Pastikan storage link & izin..."
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data storage bootstrap/cache public/build

log "Clear cache Laravel..."
"$PHP_BIN" artisan optimize:clear

log "Build cache produksi..."
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

log "Restart queue worker..."
"$PHP_BIN" artisan queue:restart

log "Reload PHP-FPM..."
systemctl reload "$PHP_FPM_SERVICE"

log "Deploy selesai."
