#!/usr/bin/env bash
# Installer OpenWA self-hosted SAJA (untuk VPS terpisah dari ERP).
# Jalankan: sudo bash install-openwa-only.sh
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CFG_DIR="${SCRIPT_DIR}/config"

# shellcheck source=scripts/lib.sh
source "${SCRIPT_DIR}/scripts/lib.sh"

trap 'rc=$?; printf "%b[FAIL] Baris %s gagal (exit %s)%b\n" "$RED" "$LINENO" "$rc" "$NC" >&2; exit "$rc"' ERR

[[ $EUID -eq 0 ]] || fail "Jalankan sebagai root: sudo bash install-openwa-only.sh"

printf '%b== INSTALLER OPENWA STANDALONE ==%b\n' "$GREEN" "$NC"

ask WA_DOMAIN "Domain/subdomain panel OpenWA (mis. wa.tokobaru.com)"
[[ "$WA_DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]] || fail "Domain tidak valid."
ask EMAIL "Email untuk Let's Encrypt"
[[ "$EMAIL" == *"@"* ]] || fail "Email tidak valid."
ask TIMEZONE "Timezone" "Asia/Jakarta"
API_MASTER_KEY="$(genpass)"

apt-get update -qq
apt_install curl ca-certificates git gnupg lsb-release \
    software-properties-common apt-transport-https nginx certbot \
    python3-certbot-nginx docker.io docker-compose-plugin jq openssl ufw

timedatectl set-timezone "$TIMEZONE" >/dev/null 2>&1 || true
systemctl enable --now docker >/dev/null 2>&1 || true

install -d -m 0755 /var/www/certbot
rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/openwa-bootstrap <<EOF
server {
    listen 80;
    server_name ${WA_DOMAIN};
    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 404; }
}
EOF
ln -sf /etc/nginx/sites-available/openwa-bootstrap /etc/nginx/sites-enabled/openwa-bootstrap
nginx -t
service_restart nginx

certbot certonly --webroot -w /var/www/certbot \
    --non-interactive --agree-tos -m "$EMAIL" --keep-until-expiring \
    -d "$WA_DOMAIN" || fail "Gagal mendapatkan sertifikat."

install -d -m 0755 /opt/openwa
cp "${CFG_DIR}/openwa/docker-compose.yml" /opt/openwa/docker-compose.yml
render "${CFG_DIR}/openwa/.env.tpl" /opt/openwa/.env \
    API_MASTER_KEY "$API_MASTER_KEY" \
    WA_DOMAIN "$WA_DOMAIN" \
    TIMEZONE "$TIMEZONE"

cd /opt/openwa
docker compose up -d --pull always

for _ in $(seq 1 30); do
    curl -sf http://127.0.0.1:2785/api/health >/dev/null 2>&1 && break
    sleep 2
done
curl -sf http://127.0.0.1:2785/api/health >/dev/null || fail "OpenWA tidak siap."

OPENWA_KEY="$(docker exec openwa-api cat /app/data/.api-key 2>/dev/null || printf '%s' "$API_MASTER_KEY")"

SESSION_JSON="$(curl -sf http://127.0.0.1:2785/api/sessions -H "X-API-Key: ${OPENWA_KEY}" || printf '[]')"
SESSION_ID="$(printf '%s' "$SESSION_JSON" | jq -r '.[0].id // empty')"
if [[ -z "$SESSION_ID" ]]; then
    SESSION_JSON="$(curl -sf -X POST http://127.0.0.1:2785/api/sessions \
        -H 'Content-Type: application/json' \
        -H "X-API-Key: ${OPENWA_KEY}" \
        -d '{"name":"toko-utama"}' || printf '{}')"
    SESSION_ID="$(printf '%s' "$SESSION_JSON" | jq -r '.id // empty')"
fi

rm -f /etc/nginx/sites-enabled/openwa-bootstrap
render "${CFG_DIR}/nginx-openwa.conf.tpl" /etc/nginx/sites-available/openwa \
    WA_DOMAIN "$WA_DOMAIN"
ln -sf /etc/nginx/sites-available/openwa /etc/nginx/sites-enabled/openwa
nginx -t
service_restart nginx

ufw allow OpenSSH >/dev/null || true
ufw allow 80/tcp >/dev/null || true
ufw allow 443/tcp >/dev/null || true
ufw --force enable >/dev/null || true

CRED=/root/openwa-credentials.txt
cat > "$CRED" <<EOF
OpenWA standalone - $(date '+%F %T')
URL       : https://${WA_DOMAIN}
API key   : ${OPENWA_KEY}
Session ID: ${SESSION_ID:-<lihat dashboard>}
Data      : /opt/openwa/data
EOF
chmod 0600 "$CRED"

ok "OPENWA STANDALONE SELESAI"
printf '\n'
printf 'Dashboard : https://%s\n' "$WA_DOMAIN"
printf 'Kredensial: %s\n' "$CRED"
printf '\nDi ERP isi Provider=OpenWA, URL=https://%s/api, Token=API key, ID Sesi=UUID.\n' "$WA_DOMAIN"
printf 'Jangan lupa scan QR di menu Sesi sampai status READY.\n'
