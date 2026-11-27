#!/usr/bin/env bash
# Backup harian UTEParts + opsional OpenWA. Dipasang oleh install.sh.
set -Eeuo pipefail

APP_DIR="{{APP_DIR}}"
BACKUP_DIR="{{BACKUP_DIR}}"
OPENWA_DATA="{{OPENWA_DATA}}"
RETENTION_DAYS="{{RETENTION_DAYS}}"
STAMP="$(date +%F_%H%M%S)"
WORK="${BACKUP_DIR}/tmp-${STAMP}"
FINAL="${BACKUP_DIR}/ute-${STAMP}.tar.gz"

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }

mkdir -p "$WORK" "$BACKUP_DIR"
chmod 0700 "$BACKUP_DIR" "$WORK"

log "Membaca .env aplikasi..."
set -a
# shellcheck disable=SC1091
source "${APP_DIR}/.env"
set +a

log "Dump database ${DB_DATABASE}..."
export MYSQL_PWD="${DB_PASSWORD}"
mariadb-dump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    "${DB_DATABASE}" > "${WORK}/database.sql"
unset MYSQL_PWD

log "Menyalin storage & .env..."
cp -a "${APP_DIR}/storage" "${WORK}/storage"
cp -a "${APP_DIR}/.env" "${WORK}/env.production"

if [[ -d "${OPENWA_DATA}" ]]; then
    log "Menyalin data OpenWA..."
    cp -a "${OPENWA_DATA}" "${WORK}/openwa-data"
fi

log "Membuat arsip..."
tar -czf "${FINAL}" -C "${WORK}" .
rm -rf "${WORK}"
chmod 0600 "${FINAL}"

log "Arsip: ${FINAL}"

if command -v rclone >/dev/null 2>&1 && [[ -n "${RCLONE_REMOTE:-}" ]]; then
    log "Upload ke rclone: ${RCLONE_REMOTE}"
    rclone copyto "${FINAL}" "${RCLONE_REMOTE}/$(basename "${FINAL}")" --no-traverse
fi

log "Hapus backup lama (> ${RETENTION_DAYS} hari)..."
find "${BACKUP_DIR}" -maxdepth 1 -type f -name 'ute-*.tar.gz' -mtime "+${RETENTION_DAYS}" -delete

log "Selesai."
