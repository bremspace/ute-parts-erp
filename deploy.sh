#!/usr/bin/env bash
# ============================================================
# Ute Parts — Deploy Script (otomatis dari GitHub Actions)
# Dipanggil di VPS via SSH. WAJIB aman: jika satu langkah gagal,
# site kembali ke versi sebelumnya (rollback) — "live" selalu terjaga.
#
# Env yang dibutuhkan (dipass oleh workflow):
#   DEPLOY_PATH  = docroot project (mis. /home/ute/ute-parts)
#   PHP_BIN      = path PHP CLI (mis. /usr/local/lsws/lsphp85/bin/php)
#   QUEUE_NAME   = nama supervisor program queue (ute-parts-queue)
# ============================================================
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH tidak diset}"
PHP_BIN="${PHP_BIN:-php}"
QUEUE_NAME="${QUEUE_NAME:-ute-parts-queue}"
LOG="/tmp/ute-deploy.log"

log()  { echo "$(date '+%F %T') | $*" | tee -a "$LOG"; }

cd "$DEPLOY_PATH"

# --- 0. Kirim log mulai ---
log "=== DEPLOY MULAI ==="

# --- Simpan revisi sekarang untuk rollback ---
OLD_REV="$(git rev-parse HEAD)"
log "Versi lama: $OLD_REV"

# --- Backup build asset lama (rollback CSS/JS jika build gagal) ---
if [ -d public/build ]; then
    mv public/build /tmp/ute-build-backup
    log "Backup public/build -> /tmp/ute-build-backup"
fi

rollback() {
    local stage="$1"
    log "!!! ROLLBACK pada tahap: $stage !!!"
    set +e
    git reset --hard "$OLD_REV" 2>>"$LOG"
    # sembunyikan build backup bila git reset tidak menghidupkan build
    if [ -d /tmp/ute-build-backup ] && [ ! -d public/build ]; then
        mv /tmp/ute-build-backup public/build
    fi
    "$PHP_BIN" artisan config:clear >>"$LOG" 2>&1
    "$PHP_BIN" artisan view:clear >>"$LOG" 2>&1
    if [ -d vendor ]; then
        composer install --no-interaction --prefer-dist --no-dev --quiet >>"$LOG" 2>&1
    fi
    chown -R lsadm:lsadm storage bootstrap/cache public/build 2>>"$LOG" || true
    set -e
    log "ROLLBACK SELESAI -> $OLD_REV. Site tetap live."
    exit 1
}

# --- 1. Pull kode terbaru ---
log "Fetch & pull origin/main..."
git stash --include-untracked >>"$LOG" 2>&1 || true          # amankan modif lokal tak terduga
git fetch origin main >>"$LOG" 2>&1 || rollback "git fetch"
git merge --ff-only origin/main >>"$LOG" 2>&1 || rollback "git merge"

NEW_REV="$(git rev-parse HEAD)"
if [ "$NEW_REV" = "$OLD_REV" ]; then
    log "Tidak ada perubahan — selesai."
    exit 0
fi
log "Versi baru: $NEW_REV"

# --- 2. Dependency PHP ---
log "composer install..."
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader >>"$LOG" 2>&1 || rollback "composer install"

# --- 3. Migrasi database ---
log "php artisan migrate --force..."
"$PHP_BIN" artisan migrate --force >>"$LOG" 2>&1 || rollback "migrate"

# --- 4. Build asset (CSS/JS) ---
log "npm install & build..."
if command -v npm >/dev/null 2>&1; then
    npm install --ignore-scripts --no-audit --no-fund >>"$LOG" 2>&1 || rollback "npm install"
    npm run build >>"$LOG" 2>&1 || rollback "npm run build"
else
    # Tidak ada npm di server → CBA: pakai build yang sudah di-backup
    if [ -d /tmp/ute-build-backup ]; then
        mv /tmp/ute-build-backup public/build
        log "npm tidak tersedia — mengembalikan build lama."
    else
        rollback "npm build (tidak ada npm & build lama)"
    fi
fi

# --- 5. Cache produksi ---
log "config/route/view cache..."
"$PHP_BIN" artisan config:cache >>"$LOG" 2>&1 || rollback "config:cache"
"$PHP_BIN" artisan route:cache >>"$LOG" 2>&1 || rollback "route:cache"
"$PHP_BIN" artisan view:cache >>"$LOG" 2>&1 || rollback "view:cache"

# --- 6. Permission (OpenLiteSpeed = lsadm) ---
log "chown lsadm..."
chown -R lsadm:lsadm storage bootstrap/cache public/build 2>>"$LOG" || true
chmod -R 775 storage bootstrap/cache 2>>"$LOG" || true

# --- 7. Restart queue worker (biar job baru terpakai) ---
log "Restart queue ($QUEUE_NAME)..."
if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart "$QUEUE_NAME" >>"$LOG" 2>&1 || log "supervisorctl restart gagal — periksa manual (site tetap live)"
fi

# --- 8. Bersihkan backup build sementara ---
rm -rf /tmp/ute-build-backup 2>>"$LOG" || true

log "=== DEPLOY SUKSES -> $NEW_REV ==="