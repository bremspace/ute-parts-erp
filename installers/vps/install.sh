#!/usr/bin/env bash
# Installer produksi UTEParts ERP + (opsional) OpenWA self-hosted.
# Target: Ubuntu 24.04 LTS. Jalankan: sudo bash install.sh
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
CFG_DIR="${SCRIPT_DIR}/config"
SCRIPTS_DIR="${SCRIPT_DIR}/scripts"

# shellcheck source=scripts/lib.sh
source "${SCRIPTS_DIR}/lib.sh"

trap 'rc=$?; printf "%b[FAIL] Baris %s gagal (exit %s)%b\n" "$RED" "$LINENO" "$rc" "$NC" >&2; exit "$rc"' ERR

[[ $EUID -eq 0 ]] || fail "Jalankan sebagai root: sudo bash install.sh"

if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    source /etc/os-release
    log "Sistem: ${PRETTY_NAME:-unknown}"
    [[ "${ID:-}" == "ubuntu" ]] || warn "Installer ini didesain untuk Ubuntu. Lanjutkan dengan risiko Anda."
else
    warn "Tidak dapat mendeteksi OS."
fi

printf '%b'
printf '%b========================================%b\n' "$BLUE" "$NC"
printf '%b INSTALLER PRODUKSI UTEPARTS ERP%b\n' "$GREEN" "$NC"
printf '%b Ubuntu 24.04 + Nginx + PHP-FPM + MariaDB + Redis%b\n' "$BLUE" "$NC"
printf '%b========================================%b\n' "$BLUE" "$NC"
printf '%b'

# ---------------------------------------------------------------- input
ask DOMAIN "Domain ERP (mis. tokobaru.com)"
[[ "$DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]] || fail "Domain tidak valid."

if confirm "Sertakan sertifikat untuk www.${DOMAIN}?" n; then
    WITH_WWW=yes
    SERVER_NAMES="${DOMAIN} www.${DOMAIN}"
    CERT_DOMAINS_ARGS=(-d "$DOMAIN" -d "www.$DOMAIN")
else
    WITH_WWW=no
    SERVER_NAMES="$DOMAIN"
    CERT_DOMAINS_ARGS=(-d "$DOMAIN")
fi

ask EMAIL "Email untuk Let's Encrypt"
[[ "$EMAIL" == *"@"* ]] || fail "Email tidak valid."

ask APP_DIR "Path instalasi aplikasi" "/var/www/ute-parts-erp"
ask REPO_URL "URL git repository" "https://github.com/bremspace/ute-parts-erp.git"
ask REPO_BRANCH "Branch" "main"
ask WA_DOMAIN "Subdomain panel OpenWA" "wa.${DOMAIN}"
ask TIMEZONE "Timezone" "Asia/Jakarta"

PUBLIC_IP="$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
[[ -n "$PUBLIC_IP" ]] || PUBLIC_IP="$(curl -fsS --max-time 5 https://ifconfig.me/ip 2>/dev/null || true)"
PRIVATE_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
ask SERVER_IP "IP publik VPS yang dipakai untuk akses (deteksi: ${PUBLIC_IP:-tidak terdeteksi})" "$PUBLIC_IP"
[[ -n "$SERVER_IP" ]] || fail "IP publik wajib diisi agar akses via IP bisa dikonfigurasi."

if confirm "Pasang OpenWA self-hosted di VPS ini juga?" y; then
    OPENWA_LOCAL=yes
else
    OPENWA_LOCAL=no
fi

DB_NAME="uteparts_db"
DB_USER="uteparts"
DB_PASS="$(genpass)"
REDIS_PASS="$(genpass)"
API_MASTER_KEY="$(genpass)"
APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
BACKUP_DIR="/var/backups/ute-parts"
RETENTION_DAYS=7

RAM_TOTAL_MB="$(ram_mb)"
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    BUFFER_MB=$(( RAM_TOTAL_MB * 35 / 100 ))
else
    BUFFER_MB=$(( RAM_TOTAL_MB * 55 / 100 ))
fi
(( BUFFER_MB < 1024 )) && BUFFER_MB=1024
(( BUFFER_MB > 8192 )) && BUFFER_MB=8192

REDIS_MAX_MB=$(( RAM_TOTAL_MB * 20 / 100 ))
(( REDIS_MAX_MB < 256 )) && REDIS_MAX_MB=256
(( REDIS_MAX_MB > 4096 )) && REDIS_MAX_MB=4096

PHP_MAX_CHILDREN=$(( RAM_TOTAL_MB / 200 ))
(( PHP_MAX_CHILDREN < 10 )) && PHP_MAX_CHILDREN=10
(( PHP_MAX_CHILDREN > 40 )) && PHP_MAX_CHILDREN=40
PHP_START_SERVERS=$(( PHP_MAX_CHILDREN / 4 ))
(( PHP_START_SERVERS < 2 )) && PHP_START_SERVERS=2
PHP_MIN_SPARE=$PHP_START_SERVERS
PHP_MAX_SPARE=$(( PHP_START_SERVERS * 2 ))

ok "RAM terdeteksi: ${RAM_TOTAL_MB} MB"
log "MariaDB buffer pool : ${BUFFER_MB}M"
log "Redis maxmemory      : ${REDIS_MAX_MB}mb"
log "PHP-FPM max children : ${PHP_MAX_CHILDREN}"
log "OpenWA lokal         : ${OPENWA_LOCAL}"

confirm "Lanjutkan instalasi dengan konfigurasi di atas?" y || fail "Dibatalkan oleh pengguna."

# ---------------------------------------------------------------- base
log "== Instal paket dasar =="
apt-get update -qq
apt_install curl ca-certificates git unzip zip gnupg lsb-release \
    software-properties-common apt-transport-https jq openssl cron ufw fail2ban

timedatectl set-timezone "$TIMEZONE" >/dev/null 2>&1 || warn "Gagal set timezone via timedatectl."

# ---------------------------------------------------------------- PHP
log "== Instal PHP-FPM =="
add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
apt-get update -qq

if apt-cache policy php8.4-fpm 2>/dev/null | awk '/Candidate:/{print $2}' | grep -qv '^(none)$'; then
    PHP_VERSION="8.4"
else
    warn "PHP 8.4 tidak tersedia, pakai 8.3."
    PHP_VERSION="8.3"
fi

PHP_PKGS=(
    "php${PHP_VERSION}-fpm"
    "php${PHP_VERSION}-cli"
    "php${PHP_VERSION}-mysql"
    "php${PHP_VERSION}-redis"
    "php${PHP_VERSION}-gd"
    "php${PHP_VERSION}-bcmath"
    "php${PHP_VERSION}-intl"
    "php${PHP_VERSION}-mbstring"
    "php${PHP_VERSION}-xml"
    "php${PHP_VERSION}-curl"
    "php${PHP_VERSION}-zip"
    "php${PHP_VERSION}-imagick"
    "php${PHP_VERSION}-opcache"
)
apt_install "${PHP_PKGS[@]}" composer

PHP_BIN="/usr/bin/php${PHP_VERSION}"
PHP_FPM_BIN="/usr/sbin/php-fpm${PHP_VERSION}"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
PHP_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"

install -d -m 0755 /etc/php/${PHP_VERSION}/fpm/conf.d
cp "${CFG_DIR}/php-opcache.ini" /etc/php/${PHP_VERSION}/fpm/conf.d/99-ute-opcache.ini

render "${CFG_DIR}/php-fpm-www.conf.tpl" /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf \
    PHP_SOCKET "$PHP_SOCKET" \
    PHP_MAX_CHILDREN "$PHP_MAX_CHILDREN" \
    PHP_START_SERVERS "$PHP_START_SERVERS" \
    PHP_MIN_SPARE "$PHP_MIN_SPARE" \
    PHP_MAX_SPARE "$PHP_MAX_SPARE"

log "Uji & auto-fix konfigurasi PHP-FPM..."
FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
FPM_OK=no
for _attempt in 1 2 3 4 5 6; do
    if "$PHP_FPM_BIN" -t > /tmp/ute-php-fpm-test.log 2>&1; then
        FPM_OK=yes
        break
    fi

    cat /tmp/ute-php-fpm-test.log
    unknown_key="$(grep -o "unknown entry '[^']*'" /tmp/ute-php-fpm-test.log \
        | head -1 | sed -E "s/.*'([^']*)'.*/\1/" || true)"

    if [[ -z "$unknown_key" ]]; then
        fail "Konfigurasi PHP-FPM tidak valid dengan error di atas (bukan directive unknown)."
    fi

    warn "PHP-FPM ${PHP_VERSION} tidak mengenal '$unknown_key' — menghapus otomatis..."
    strip_fpm_directive "$FPM_CONF" "$unknown_key"
done

if [[ "$FPM_OK" != "yes" ]]; then
    cat /tmp/ute-php-fpm-test.log
    fail "Gagal auto-fix konfigurasi PHP-FPM setelah beberapa percobaan."
fi
ok "Konfigurasi PHP-FPM valid."

service_restart "$PHP_FPM_SERVICE"

# ---------------------------------------------------------------- MariaDB
log "== Instal MariaDB 11.4 LTS =="
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup -o /tmp/mariadb_repo_setup
chmod +x /tmp/mariadb_repo_setup
/tmp/mariadb_repo_setup --mariadb-server-version="mariadb-11.4" --skip-maxscale >/dev/null 2>&1
apt-get update -qq
apt_install mariadb-server

render "${CFG_DIR}/mariadb-tuning.cnf.tpl" /etc/mysql/mariadb.conf.d/99-ute-tuning.cnf \
    BUFFER_POOL "${BUFFER_MB}M"

service_restart mariadb
for _ in $(seq 1 30); do
    mariadb-admin ping >/dev/null 2>&1 && break
    sleep 1
done
mariadb-admin ping >/dev/null 2>&1 || fail "MariaDB tidak merespons."

log "Membuat database & user..."
mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------- Redis
log "== Instal & tuning Redis =="
apt_install redis-server
REDIS_CONF=/etc/redis/redis.conf
cp "$REDIS_CONF" "${REDIS_CONF}.bak-installer" 2>/dev/null || true
sed -i 's/^bind 127.0.0.1 ::1/bind 127.0.0.1/' "$REDIS_CONF"
sed -i "s/^# maxmemory .*/maxmemory ${REDIS_MAX_MB}mb/" "$REDIS_CONF"
sed -i "s/^maxmemory .*/maxmemory ${REDIS_MAX_MB}mb/" "$REDIS_CONF"
sed -i 's/^# maxmemory-policy .*/maxmemory-policy volatile-lru/' "$REDIS_CONF"
sed -i 's/^maxmemory-policy .*/maxmemory-policy volatile-lru/' "$REDIS_CONF"
sed -i "s/^# requirepass .*/requirepass ${REDIS_PASS}/" "$REDIS_CONF"
sed -i "s/^requirepass .*/requirepass ${REDIS_PASS}/" "$REDIS_CONF"
service_restart redis-server

# ---------------------------------------------------------------- App
log "== Pasang aplikasi UTEParts =="
install -d -m 0755 "$(dirname "$APP_DIR")"
if [[ -d "${APP_DIR}/.git" ]]; then
    log "Repo sudah ada, tarik ulang dari ${REPO_BRANCH}..."
    git -C "$APP_DIR" fetch --all --prune
    git -C "$APP_DIR" reset --hard "origin/${REPO_BRANCH}"
else
    git clone --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
fi

render "${CFG_DIR}/env-production.env" "${APP_DIR}/.env" \
    APP_KEY "$APP_KEY" \
    APP_URL "https://${DOMAIN}" \
    TIMEZONE "$TIMEZONE" \
    DB_NAME "$DB_NAME" \
    DB_USER "$DB_USER" \
    DB_PASS "$DB_PASS" \
    REDIS_PASS "$REDIS_PASS" \
    DOMAIN "$DOMAIN"
chmod 0640 "${APP_DIR}/.env"
chown root:www-data "${APP_DIR}/.env"

cd "$APP_DIR"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force --no-interaction || warn "Seeder dilewati/gagal (bisa normal bila data sudah ada)."
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data storage bootstrap/cache public/build

# ---------------------------------------------------------------- Nginx + TLS
log "== Pasang Nginx + sertifikat =="
apt_install nginx certbot python3-certbot-nginx
rm -f /etc/nginx/sites-enabled/default
install -d -m 0755 /var/www/certbot/.well-known/acme-challenge

BOOT_CHALLENGE=$(cat <<'CHUNK'
    location /.well-known/acme-challenge/ {
        alias /var/www/certbot/.well-known/acme-challenge/;
        default_type "text/plain";
        add_header Cache-Control "no-store" always;
        try_files $uri =404;
    }
CHUNK
)

cat > /etc/nginx/sites-available/ute-parts-bootstrap <<EOF
server {
    listen 80;
    server_name ${SERVER_NAMES};
${BOOT_CHALLENGE}
    location / { return 404; }
}
EOF

if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    cat > /etc/nginx/sites-available/openwa-bootstrap <<EOF
server {
    listen 80;
    server_name ${WA_DOMAIN};
${BOOT_CHALLENGE}
    location / { return 404; }
}
EOF
    ln -sf /etc/nginx/sites-available/openwa-bootstrap /etc/nginx/sites-enabled/openwa-bootstrap
fi

ln -sf /etc/nginx/sites-available/ute-parts-bootstrap /etc/nginx/sites-enabled/ute-parts-bootstrap
nginx -t
service_restart nginx

CERT_OK=yes
if ! certbot certonly --webroot -w /var/www/certbot \
    --non-interactive --agree-tos -m "$EMAIL" --keep-until-expiring \
    "${CERT_DOMAINS_ARGS[@]}"; then
    CERT_OK=no
    warn "Gagal sertifikat Let's Encrypt untuk ${DOMAIN}. Installer lanjut dengan self-signed."
fi

WA_CERT_OK=yes
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    if ! certbot certonly --webroot -w /var/www/certbot \
        --non-interactive --agree-tos -m "$EMAIL" --keep-until-expiring \
        -d "$WA_DOMAIN"; then
        WA_CERT_OK=no
        warn "Gagal sertifikat Let's Encrypt untuk ${WA_DOMAIN}. Installer lanjut dengan self-signed."
    fi
fi

# Tentukan sertifikat ERP.
ERP_SAN_DOMAINS=("$DOMAIN")
[[ "$WITH_WWW" == "yes" ]] && ERP_SAN_DOMAINS+=("www.$DOMAIN")

if [[ "$CERT_OK" == "yes" ]]; then
    SSL_CERT="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
    SSL_KEY="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"
else
    make_selfsigned "erp" "${ERP_SAN_DOMAINS[@]}"
fi

# Sertifikat untuk akses via IP. Let's Encrypt tidak mencakup alamat IP,
# jadi selalu pakai self-signed dengan SAN IP publik + IP privat.
IP_SAN_LIST=("$SERVER_IP")
[[ -n "${PRIVATE_IP:-}" && "$PRIVATE_IP" != "$SERVER_IP" ]] && IP_SAN_LIST+=("$PRIVATE_IP")
make_selfsigned "erp-ip" "${IP_SAN_LIST[@]}"
IP_SSL_CERT="$SSL_CERT"
IP_SSL_KEY="$SSL_KEY"

rm -f /etc/nginx/sites-enabled/ute-parts-bootstrap
render "${CFG_DIR}/nginx-ute.conf.tpl" /etc/nginx/sites-available/ute-parts \
    DOMAIN "$DOMAIN" \
    SERVER_NAMES "$SERVER_NAMES" \
    APP_DIR "$APP_DIR" \
    PHP_SOCKET "$PHP_SOCKET" \
    SSL_CERT "$SSL_CERT" \
    SSL_KEY "$SSL_KEY" \
    IP_SSL_CERT "$IP_SSL_CERT" \
    IP_SSL_KEY "$IP_SSL_KEY"
ln -sf /etc/nginx/sites-available/ute-parts /etc/nginx/sites-enabled/ute-parts

# Tentukan sertifikat OpenWA.
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    if [[ "$WA_CERT_OK" == "yes" ]]; then
        WA_SSL_CERT="/etc/letsencrypt/live/${WA_DOMAIN}/fullchain.pem"
        WA_SSL_KEY="/etc/letsencrypt/live/${WA_DOMAIN}/privkey.pem"
    else
        make_selfsigned "openwa" "$WA_DOMAIN"
        WA_SSL_CERT="$SSL_CERT"
        WA_SSL_KEY="$SSL_KEY"
    fi

    rm -f /etc/nginx/sites-enabled/openwa-bootstrap
    render "${CFG_DIR}/nginx-openwa.conf.tpl" /etc/nginx/sites-available/openwa \
        WA_DOMAIN "$WA_DOMAIN" \
        SSL_CERT "$WA_SSL_CERT" \
        SSL_KEY "$WA_SSL_KEY"
    ln -sf /etc/nginx/sites-available/openwa /etc/nginx/sites-enabled/openwa
else
    WA_DOMAIN=""
fi

if [[ "$CERT_OK" == "no" || "$WA_CERT_OK" == "no" ]]; then
    SSL_NOTE=/root/ute-ssl-warning.txt
    cat > "$SSL_NOTE" <<EOF
SERTIFIKAT LET'S ENCRYPT BELUM AKTIF - $(date '+%F %T')

Installer tetap lanjut memakai sertifikat self-signed sementara.
Browser akan menampilkan peringatan "Not secure" sampai sertifikat asli dipasang.

Akses tetap bisa lewat:
- Domain: https://${DOMAIN}
- IP HTTP: http://${SERVER_IP}  (NAT external -> VPS port 80)
- IP HTTPS: https://${SERVER_IP} (NAT external -> VPS port 443, self-signed)

Penyebab umum:
- Domain masih di-proxy Cloudflare (orange cloud). Challenge HTTP-01 tidak sampai ke VPS.
- A record belum mengarah ke IP VPS ini.
- Port 80 diblokir dari internet / Cloudflare.

Solusi:
1. Di Cloudflare DNS, set record ${DOMAIN} dan www.${DOMAIN} ke "DNS only" (abu-abu),
   atau pastikan Cloudflare bisa menjangkau port 80 VPS Anda.
2. Tunggu DNS propagate, lalu jalankan:
   sudo certbot certonly --webroot -w /var/www/certbot \\
     --non-interactive --agree-tos -m ${EMAIL} --keep-until-expiring \\
     ${CERT_DOMAINS_ARGS[*]}
3. Setelah sukses:
   sudo ute-deploy
   sudo systemctl reload nginx

Untuk OpenWA (jika dipasang lokal):
   sudo certbot certonly --webroot -w /var/www/certbot \\
     --non-interactive --agree-tos -m ${EMAIL} --keep-until-expiring \\
     -d ${WA_DOMAIN:-wa.${DOMAIN}}
   sudo systemctl reload nginx
EOF
    chmod 0600 "$SSL_NOTE"
    warn "Detail sertifikat: ${SSL_NOTE}"
fi

nginx -t
service_restart nginx

# ---------------------------------------------------------------- Supervisor
log "== Pasang Supervisor queue worker =="
apt_install supervisor
render "${CFG_DIR}/supervisor-ute.conf.tpl" /etc/supervisor/conf.d/ute-parts.conf \
    APP_DIR "$APP_DIR" \
    PHP_BIN "$PHP_BIN"
mkdir -p /var/log/supervisor
service_restart supervisor
supervisorctl reread >/dev/null 2>&1 || true
supervisorctl update >/dev/null 2>&1 || true

# ---------------------------------------------------------------- Cron
render "${CFG_DIR}/cron-ute.tpl" /etc/cron.d/ute-parts \
    APP_DIR "$APP_DIR" \
    PHP_BIN "$PHP_BIN"
chmod 0644 /etc/cron.d/ute-parts
service_restart cron

# ---------------------------------------------------------------- Firewall
log "== Firewall & fail2ban =="
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
service_restart fail2ban

# ---------------------------------------------------------------- OpenWA
OPENWA_KEY=""
SESSION_ID=""
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    log "== Pasang OpenWA self-hosted =="
    install_docker

    install -d -m 0755 /opt/openwa
    cp "${CFG_DIR}/openwa/docker-compose.yml" /opt/openwa/docker-compose.yml
    render "${CFG_DIR}/openwa/.env.tpl" /opt/openwa/.env \
        API_MASTER_KEY "$API_MASTER_KEY" \
        WA_DOMAIN "$WA_DOMAIN" \
        TIMEZONE "$TIMEZONE"

    cd /opt/openwa
    docker compose up -d --pull always

    log "Menunggu OpenWA siap..."
    for _ in $(seq 1 30); do
        curl -sf http://127.0.0.1:2785/api/health >/dev/null 2>&1 && break
        sleep 2
    done
    curl -sf http://127.0.0.1:2785/api/health >/dev/null || fail "OpenWA tidak siap setelah 60 detik."

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
fi

# ---------------------------------------------------------------- Helper scripts
log "== Pasang skrip operasional =="
render "${SCRIPTS_DIR}/deploy.sh.tpl" /usr/local/bin/ute-deploy \
    APP_DIR "$APP_DIR" \
    PHP_BIN "$PHP_BIN" \
    PHP_FPM_SERVICE "$PHP_FPM_SERVICE"
chmod 0755 /usr/local/bin/ute-deploy

render "${SCRIPTS_DIR}/backup.sh.tpl" /usr/local/bin/ute-backup \
    APP_DIR "$APP_DIR" \
    BACKUP_DIR "$BACKUP_DIR" \
    OPENWA_DATA "/opt/openwa/data" \
    RETENTION_DAYS "$RETENTION_DAYS"
chmod 0755 /usr/local/bin/ute-backup
install -d -m 0700 "$BACKUP_DIR"

render "${SCRIPTS_DIR}/healthcheck.sh.tpl" /usr/local/bin/ute-healthcheck \
    DOMAIN "$DOMAIN" \
    SERVER_IP "$SERVER_IP" \
    WA_DOMAIN "$WA_DOMAIN" \
    APP_DIR "$APP_DIR" \
    PHP_BIN "$PHP_BIN" \
    PHP_VERSION "$PHP_VERSION"
chmod 0755 /usr/local/bin/ute-healthcheck

cat > /etc/cron.d/ute-backup <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
15 2 * * * root /usr/local/bin/ute-backup >> /var/log/ute-backup.log 2>&1
EOF
chmod 0644 /etc/cron.d/ute-backup

# ---------------------------------------------------------------- Optimize
log "== Optimize Laravel =="
cd "$APP_DIR"
"$PHP_BIN" artisan optimize:clear || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

# ---------------------------------------------------------------- Credentials
CRED_FILE=/root/ute-install-credentials.txt
cat > "$CRED_FILE" <<EOF
UTEParts VPS install - $(date '+%F %T')
Domain ERP      : https://${DOMAIN}
Akses IP HTTP   : http://${SERVER_IP}  (NAT: port 80 -> VPS 80)
Akses IP HTTPS  : https://${SERVER_IP} (NAT: port 443 -> VPS 443, self-signed)
Panel OpenWA    : https://${WA_DOMAIN:-<tidak dipasang lokal>}
App path        : ${APP_DIR}
PHP             : ${PHP_VERSION}
DB name/user    : ${DB_NAME} / ${DB_USER}
DB password     : ${DB_PASS}
Redis password  : ${REDIS_PASS}
API master key  : ${API_MASTER_KEY:-<tidak dipakai>}
APP_KEY         : ${APP_KEY}
OpenWA API key  : ${OPENWA_KEY:-<tidak dipasang lokal>}
OpenWA session  : ${SESSION_ID:-<buat/scan manual>}
Backup dir      : ${BACKUP_DIR}
EOF
chmod 0600 "$CRED_FILE"

# ---------------------------------------------------------------- OpenWA note
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    cat > /root/ute-openwa-next.txt <<EOF
LANGKAH SETELAH INSTALL OPENWA
1. Buka https://${WA_DOMAIN}
2. Login pakai API key: ${OPENWA_KEY}
3. Buka menu Sesi -> sesi 'toko-utama' -> Start -> scan QR dengan HP.
4. Setelah status READY, salin ID sesi lengkap (UUID): ${SESSION_ID:-<lihat dashboard>}
5. Cara paling mudah (ERP terbaru sudah punya 1-klik):
   Buka Pengaturan -> Integrasi -> WhatsApp Gateway, pilih Provider OpenWA,
   lalu tempel Master Admin Key di panel "Setup Otomatis 1-Klik".
   ERP akan membuat sesi, API key operator, dan menyimpannya terenkripsi.
6. Scan QR langsung di panel ERP sampai status Terhubung & Siap.
7. Klik Uji Coba WhatsApp.

Cara manual tetap tersedia: isi URL http://127.0.0.1:2785/api,
Token = API key di atas, ID Sesi = UUID dari langkah 4.
EOF
    chmod 0600 /root/ute-openwa-next.txt
fi

# ---------------------------------------------------------------- Verify
log "== Healthcheck akhir =="
/usr/local/bin/ute-healthcheck || warn "Healthcheck melaporkan masalah; periksa output di atas."

ok "INSTALL SELESAI"
printf '\n'
printf '%bRingkasan:%b\n' "$GREEN" "$NC"
printf '  URL ERP      : https://%s\n' "$DOMAIN"
printf '  Akses IP     : http://%s  (HTTP) / https://%s (self-signed)\n' "$SERVER_IP" "$SERVER_IP"
printf '  Panel WA     : %s\n' "${WA_DOMAIN:-tidak dipasang lokal}"
printf '  Kredensial   : %s\n' "$CRED_FILE"
if [[ "$OPENWA_LOCAL" == "yes" ]]; then
    printf '  Langkah WA   : /root/ute-openwa-next.txt\n'
fi
printf '  Deploy       : ute-deploy\n'
printf '  Backup       : ute-backup\n'
printf '  Healthcheck  : ute-healthcheck\n'
printf '\n'
