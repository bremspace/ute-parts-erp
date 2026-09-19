#!/usr/bin/env bash
# ============================================================
# Ute Parts ERP — VPS Installer (standalone, PHP 8.5)
#
# Flow:
#   1. Install stack (PHP 8.5 + nginx + MySQL)
#   2. Clone project ke /var/www/<domain>/
#   3. Build + configure
#   4. SSL — otomatis jika DNS siap, atau manual nanti
#   5. Output: akses via IP (langsung) + domain (setelah DNS)
#
# Jalankan:
#   bash setup-vps.sh test.uteparts.id
# ============================================================
set -euo pipefail
trap 'echo "ERROR: baris $LINENO — periksa log." >&2' ERR

DOMAIN="${1:?Usage: bash $0 domain.com}"
REPO="https://github.com/bremspace/ute-parts-erp.git"
PROJECT="/var/www/${DOMAIN}"
PUBLIC="${PROJECT}/public"
DBNAME="${DOMAIN//[^a-zA-Z0-9]/_}"

echo ""
echo "========================================"
echo "  UTE PARTS — Install: ${DOMAIN}"
echo "========================================"

# ── 1. System packages ──────────────────────────────────
echo ""
echo "[1/9] System packages..."
DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq >/dev/null

# PHP 8.5 via Ondrej PPA (jika belum ada)
if ! command -v php8.5 >/dev/null 2>&1; then
  echo "  Install PHP 8.5 via Ondrej PPA..."
  apt-get install -y software-properties-common >/dev/null 2>&1
  add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
  DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php8.5-fpm php8.5-mysql php8.5-gd php8.5-xml \
    php8.5-mbstring php8.5-zip php8.5-bcmath php8.5-intl \
    php8.5-curl php8.5-sqlite3 >/dev/null
fi
php8.5 -v >/dev/null 2>&1 || { echo "PHP 8.5 gagal install."; exit 1; }
echo "  PHP $(php8.5 -r 'echo PHP_VERSION;')"

# Nginx + MySQL + utilities
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  nginx mysql-server curl unzip >/dev/null 2>&1

# Composer 2.x (bukan apt Composer — lama & deprecated di PHP 8.5)
if ! composer --version 2>/dev/null | grep -q "Composer 2"; then
  echo "  Install Composer 2.x terbaru..."
  curl -sS https://getcomposer.org/installer | php8.5 -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
fi
echo "  Compose $(composer --version 2>/dev/null | head -c 20)"

# ── 2. MySQL DB + User ─────────────────────────────────
echo ""
echo "[2/9] Database..."
DBUSER="${DBNAME}"
DBPASS="$(openssl rand -hex 12)"

mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}';
  GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost';
  FLUSH PRIVILEGES;
" 2>/dev/null || sudo mysql -e "
  CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}';
  GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost';
  FLUSH PRIVILEGES;
" 2>/dev/null
echo "  DB: ${DBNAME} / ${DBUSER}"

# ── 3. Clone project ───────────────────────────────────
echo ""
echo "[3/9] Clone project ke ${PROJECT}..."
mkdir -p "$PROJECT"
cd "$PROJECT"
git clone --depth 1 "$REPO" . 2>/dev/null || git pull origin main

# ── 4. Dependencies + Build ────────────────────────────
echo ""
echo "[4/9] Build..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm install --ignore-scripts --no-audit --no-fund >/dev/null 2>&1 && npm run build
echo "  Assets siap"

# ── 5. .env ────────────────────────────────────────────
echo ""
echo "[5/9] Konfigurasi..."
cat > .env <<EOF
APP_NAME="Ute Parts"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://${DOMAIN}
APP_LOCALE=id
BCRYPT_ROUNDS=10
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DBNAME}
DB_USERNAME=${DBUSER}
DB_PASSWORD=${DBPASS}
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=
MAIL_MAILER=log
DUITKU_SANDBOX=true
VITE_APP_NAME="Ute Parts"
EOF

php8.5 artisan key:generate --force
echo "  Key + env siap"

# ── 6. Migrate + Seed + Cache ──────────────────────────
echo ""
echo "[6/9] Migrate & seed..."
php8.5 artisan migrate --force
php8.5 artisan db:seed --force || true
php8.5 artisan storage:link
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan view:cache
echo "  Database + cache siap"

# ── 7. Permission + Nginx ──────────────────────────────
echo ""
echo "[7/9] Permission & Nginx..."
chown -R www-data:www-data "$PROJECT"
chmod -R 755 "$PROJECT"
chmod -R 775 "$PROJECT/storage" "$PROJECT/bootstrap/cache"

cat > /etc/nginx/sites-available/ute-parts <<'NGX'
server {
    listen 80;
    listen [::]:80;
    server_name test.uteparts.id www.test.uteparts.id;
    root /var/www/test.uteparts.id/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64m;
    server_tokens off;

    access_log /var/log/nginx/ute-parts.access.log;
    error_log  /var/log/nginx/ute-parts.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGX

ln -sf /etc/nginx/sites-available/ute-parts /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
echo "  Nginx siap"

# ── 8. Supervisor (queue) ──────────────────────────────
echo ""
echo "[8/9] Queue worker..."
if command -v supervisorctl >/dev/null 2>&1; then
  mkdir -p /var/log
  cat > /etc/supervisor/conf.d/ute-parts.conf <<'SPV'
[program:ute-parts-queue]
command=php8.5 /var/www/test.uteparts.id/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=root
numprocs=1
stdout_logfile=/var/log/ute-parts-queue.log
SPV
  supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null
  echo "  Queue worker siap"
fi

# ── 9. Selesai ─────────────────────────────────────────
echo ""
echo "[9/9] Selesai!"
echo ""
echo "========================================"
echo "  PROJECT DEPLOYED"
echo "========================================"
echo "  Folder   : ${PROJECT}"
echo "  Public   : ${PUBLIC}"
echo "  Database : ${DBNAME} (${DBUSER})"
echo ""
echo "  Akses lokal (via IP):"
echo "    http://$(hostname -I | awk '{print $1}')/app/login"
echo "    User: admin@uteparts.com  Pass: password"
echo ""
echo "  SSL via domain (opsional, jalankan setelah DNS siap):"
echo "    certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"
echo "========================================"
