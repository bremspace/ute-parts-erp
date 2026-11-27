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

PUBLIC_IP="$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
[[ -n "$PUBLIC_IP" ]] || PUBLIC_IP="$(curl -fsS --max-time 5 https://ifconfig.me/ip 2>/dev/null || true)"
PRIVATE_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
ask SERVER_IP "IP publik VPS yang dipakai untuk akses (deteksi: ${PUBLIC_IP:-tidak terdeteksi})" "$PUBLIC_IP"
[[ -n "$SERVER_IP" ]] || fail "IP publik wajib diisi."

API_MASTER_KEY="$(genpass)"

apt-get update -qq
apt_install curl ca-certificates git gnupg lsb-release \
    software-properties-common apt-transport-https nginx certbot \
    python3-certbot-nginx docker.io docker-compose-plugin jq openssl ufw

timedatectl set-timezone "$TIMEZONE" >/dev/null 2>&1 || true
systemctl enable --now docker >/dev/null 2>&1 || true

install -d -m 0755 /var/www/certbot/.well-known/acme-challenge
rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/openwa-bootstrap <<EOF
server {
    listen 80;
    server_name ${WA_DOMAIN};
    location /.well-known/acme-challenge/ {
        alias /var/www/certbot/.well-known/acme-challenge/;
        default_type "text/plain";
        add_header Cache-Control "no-store" always;
        try_files \$uri =404;
    }
    location / { return 404; }
}
EOF
ln -sf /etc/nginx/sites-available/openwa-bootstrap /etc/nginx/sites-enabled/openwa-bootstrap
nginx -t
service_restart nginx

WA_CERT_OK=yes
if ! certbot certonly --webroot -w /var/www/certbot \
    --non-interactive --agree-tos -m "$EMAIL" --keep-until-expiring \
    -d "$WA_DOMAIN"; then
    WA_CERT_OK=no
    warn "Gagal sertifikat Let's Encrypt untuk ${WA_DOMAIN}. Lanjut dengan self-signed."
fi

IP_SAN_LIST=("$SERVER_IP")
[[ -n "${PRIVATE_IP:-}" && "$PRIVATE_IP" != "$SERVER_IP" ]] && IP_SAN_LIST+=("$PRIVATE_IP")
make_selfsigned "openwa-ip" "${IP_SAN_LIST[@]}"
IP_SSL_CERT="$SSL_CERT"
IP_SSL_KEY="$SSL_KEY"

if [[ "$WA_CERT_OK" == "yes" ]]; then
    SSL_CERT="/etc/letsencrypt/live/${WA_DOMAIN}/fullchain.pem"
    SSL_KEY="/etc/letsencrypt/live/${WA_DOMAIN}/privkey.pem"
else
    make_selfsigned "openwa" "$WA_DOMAIN"
    cat > /root/openwa-ssl-warning.txt <<EOF
Sertifikat Let's Encrypt gagal untuk ${WA_DOMAIN}.
Sementara memakai self-signed. Perbaiki DNS (matikan proxy Cloudflare /
arahkan A record ke VPS ini), lalu:
  sudo certbot certonly --webroot -w /var/www/certbot \\
    --non-interactive --agree-tos -m ${EMAIL} --keep-until-expiring \\
    -d ${WA_DOMAIN}
  sudo systemctl reload nginx
EOF
    chmod 0600 /root/openwa-ssl-warning.txt
fi

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
render "${CFG_DIR}/nginx-openwa-ip.conf.tpl" /etc/nginx/sites-available/openwa \
    WA_DOMAIN "$WA_DOMAIN" \
    SERVER_IP "$SERVER_IP" \
    SSL_CERT "$SSL_CERT" \
    SSL_KEY "$SSL_KEY" \
    IP_SSL_CERT "$IP_SSL_CERT" \
    IP_SSL_KEY "$IP_SSL_KEY"
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
URL domain: https://${WA_DOMAIN}
URL IP    : http://${SERVER_IP} / https://${SERVER_IP} (self-signed)
API key   : ${OPENWA_KEY}
Session ID: ${SESSION_ID:-<lihat dashboard>}
Data      : /opt/openwa/data
EOF
chmod 0600 "$CRED"

ok "OPENWA STANDALONE SELESAI"
printf '\n'
printf 'Dashboard : https://%s  |  IP: http://%s\n' "$WA_DOMAIN" "$SERVER_IP"
printf 'Kredensial: %s\n' "$CRED"
printf '\nDi ERP isi Provider=OpenWA, URL=https://%s/api, Token=API key, ID Sesi=UUID.\n' "$WA_DOMAIN"
printf 'Jangan lupa scan QR di menu Sesi sampai status READY.\n'
