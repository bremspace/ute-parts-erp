#!/usr/bin/env bash
# ============================================================
# Ute Parts — VPS Standalone Deploy (tanpa CyberPanel)
# PHP 8.5 via Ondrej PPA + Nginx + MySQL + Certbot
#
# Jalankan sebagai root di VPS:
#   bash setup-vps.sh test.uteparts.id
# ============================================================
set -euo pipefail
trap 'echo "❌ ERROR di baris $LINENO — periksa log." >&2' ERR

DOMAIN="${1:?Penggunaan: bash $0 domain.com}"
REPO="https://github.com/bremspace/ute-parts-erp.git"

echo "══════════════════════════════════════"
echo "  UTE PARTS — Deploy: ${DOMAIN}"
echo "══════════════════════════════════════"

# ── 1. System Update ──
echo "[1/8] Update packages..."
apt-get update -qq >/dev/null
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq >/dev/null

# ── 2. Install PHP 8.5 + dependencies ──
echo "[2/8] Install PHP 8.5 + nginx + MySQL..."
if ! dpkg -l php8.5-fpm 2>/dev/null | grep -q "^ii"; then
  # Tambah Ondrej PPA untuk PHP terbaru
  echo "⟳ Tambah PPA Ondrej..."
  apt-get install -y software-properties-common >/dev/null 2>&1
  add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
  apt-get update -qq >/dev/null

  echo "⟳ Install PHP 8.5 + ekstensi..."
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php8.5-fpm php8.5-mysql php8.5-gd php8.5-xml \
    php8.5-mbstring php8.5-zip php8.5-bcmath \
    php8.5-intl php8.5-curl php8.5-sqlite3 >/dev/null

  # Pastikan php8.5 jalan
  php8.5 -v >/dev/null 2>&1 || { echo "❌ PHP 8.5 gagal diinstall."; exit 1; }
  echo "✔ PHP $(php8.5 -r 'echo PHP_VERSION;')"
else
  echo "✔ PHP 8.5 sudah terinstall"
fi

# Nginx
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx certbot python3-certbot-nginx >/dev/null 2>&1

# MySQL — cek apakah sudah ada; jika belum install
if ! mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
  echo "⟳ Install MySQL..."
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server >/dev/null 2>&1
fi
echo "✔ MySQL ready"

# ── 3. MySQL DB + User ──
DBNAME="${DOMAIN//[^a-zA-Z0-9]/_}"
DBUSER="${DBNAME}"
DBPASS=$(openssl rand -hex 12)

echo "[3/8] Buat database ${DBNAME}..."
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}';
  GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost';
  FLUSH PRIVILEGES;
"
echo "✔ DB siap (user=${DBUSER})"

# ── 4. Clone project ──
echo "[4/8] Clone project..."
WEBROOT="/var/www/ute-parts"
mkdir -p "$WEBROOT"
cd "$WEBROOT"
git clone --depth 1 "$REPO" . 2>/dev/null || git pull origin main
echo "✔ Project di $WEBROOT"

# ── 5. Build ──
echo "[5/8] Install Composer 2.x (terbaru) + build assets..."
php8.5 -r "echo 'PHP OK';" >/dev/null || { echo "❌ php8.5 tidak jalan"; exit 1; }

# Install Composer 2.x terbaru via PHAR (bukan apt, supaya compatible PHP 8.5)
if ! command -v composer >/dev/null 2>&1 || ! composer --version 2>/dev/null | grep -q "Composer 2"; then
  echo "⟳ Download Composer 2.x..."
  curl -sS https://getcomposer.org/installer | php8.5 -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
  echo "✔ Composer $(composer --version 2>/dev/null | head -1)"
fi

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm install --ignore-scripts --no-audit --no-fund >/dev/null 2>&1 && npm run build
echo "✔ Assets siap"

# ── 6. .env + artisan ──
echo "[6/8] Konfigurasi .env..."
cat > .env <<EOF
APP_NAME="Ute Parts"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${DOMAIN}
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
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=${DOMAIN}
SANCTUM_STATEFUL_DOMAINS=${DOMAIN}
MAIL_MAILER=log
DUITKU_SANDBOX=true
VITE_APP_NAME="Ute Parts"
EOF

php8.5 artisan key:generate --force
echo "[6b] Migrate + seed..."
php8.5 artisan migrate --force
php8.5 artisan db:seed --force || true
php8.5 artisan storage:link
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan view:cache
echo "✔ Laravel siap"

# ── 7. Nginx ──
echo "[7/8] Nginx vhost..."
cat > /etc/nginx/sites-available/ute-parts <<'NGX'
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN_PLACEHOLDER www.DOMAIN_PLACEHOLDER;
    root /var/www/ute-parts/public;
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

sed -i "s/DOMAIN_PLACEHOLDER/${DOMAIN}/g" /etc/nginx/sites-available/ute-parts
ln -sf /etc/nginx/sites-available/ute-parts /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
echo "✔ Nginx configured"

# ── 8. SSL + Supervisor ──
echo "[8/8] SSL + queue worker..."
certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos -m "admin@${DOMAIN}" 2>/dev/null || echo "⚠️ SSL gagal — aktifkan manual via certbot"

# Supervisor queue
if command -v supervisorctl >/dev/null 2>&1; then
  cat > /etc/supervisor/conf.d/ute-parts.conf <<'SPV'
[program:ute-parts-queue]
command=php8.5 /var/www/ute-parts/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=root
numprocs=1
stdout_logfile=/var/log/ute-parts-queue.log
SPV
  supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null
  echo "✔ Queue worker"
fi

# Cron auto-migrate
CRON="* * * * * cd /var/www/ute-parts && php8.5 artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -v "schedule:run"; echo "$CRON") | crontab -
echo "✔ Scheduler cron"

# ── Done ──
echo ""
echo "============================================================"
echo "  DEPLOY SELESAI"
echo "  URL   : https://${DOMAIN}"
echo "  Login : /app/login → admin@uteparts.com / password"
echo "  PHP   : $(php8.5 -r 'echo PHP_VERSION;')"
echo "============================================================"

