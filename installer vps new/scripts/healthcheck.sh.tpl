#!/usr/bin/env bash
# Cek kesehatan cepat instalasi UTEParts.
set -Eeuo pipefail

DOMAIN="{{DOMAIN}}"
WA_DOMAIN="{{WA_DOMAIN}}"
APP_DIR="{{APP_DIR}}"
PHP_BIN="{{PHP_BIN}}"

FAIL=0

check_service() {
    if systemctl is-active --quiet "$1"; then
        printf '[ OK ] service %s\n' "$1"
    else
        printf '[FAIL] service %s tidak aktif\n' "$1"
        FAIL=1
    fi
}

check_http() {
    local url="$1" code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$url" || true)"
    if [[ "$code" =~ ^2|3 ]]; then
        printf '[ OK ] %s -> %s\n' "$url" "$code"
    else
        printf '[FAIL] %s -> %s\n' "$url" "$code"
        FAIL=1
    fi
}

log() { printf '%s\n' "$*"; }

log "=== Service ==="
check_service nginx
check_service mariadb
check_service redis-server
check_service php{{PHP_VERSION}}-fpm
check_service supervisor
check_service cron

log ""
log "=== Aplikasi ==="
cd "$APP_DIR"
if "$PHP_BIN" artisan about --only=environment >/dev/null 2>&1; then
    printf '[ OK ] artisan about\n'
else
    printf '[FAIL] artisan about\n'
    FAIL=1
fi

if "$PHP_BIN" artisan migrate:status >/dev/null 2>&1; then
    printf '[ OK ] koneksi database & migrate:status\n'
else
    printf '[FAIL] koneksi database / migrate:status\n'
    FAIL=1
fi

log ""
log "=== HTTP ==="
check_http "https://${DOMAIN}/login-pelanggan"
check_http "https://${DOMAIN}/shop"

if [[ -n "$WA_DOMAIN" ]]; then
    check_http "https://${WA_DOMAIN}/api/health"
fi

log ""
if [[ "$FAIL" -eq 0 ]]; then
    printf '%b[ OK ] Semua pemeriksaan lulus.%b\n' '\033[0;32m' '\033[0m'
else
    printf '%b[FAIL] Ada pemeriksaan yang gagal.%b\n' '\033[0;31m' '\033[0m'
fi

exit "$FAIL"
