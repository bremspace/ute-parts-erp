#!/usr/bin/env bash
# ============================================================
# Ute Parts — Universal VPS Deploy (dengan DAN tanpa CyberPanel)
#
# Jalankan di VPS sebagai root:
#   bash setup-vps.sh domain.com
#
# Script mendeteksi apakah CyberPanel ada:
#   - Jika CyberPanel: pakai OpenLiteSpeed
#   - Jika tidak: install nginx + php-fpm otomatis
#
# Hasil akhir: project berjalan di https://domain.com
# ============================================================
set -euo pipefail

DOMAIN="${1:?Usage: bash $0 domain.com}"
REPO="https://github.com/bremspace/ute-parts-erp.git"
WEBROOT="/var/www/ute-parts/public"

# ── Detect: CyberPanel atau standalone ──
HAS_CYBERPANEL=false
if [ -d "/usr/local/lsws" ] || [ -d "/usr/local/CyberCP" ]; then
  HAS_CYBERPANEL=true
  WEBROOT="/home/${DOMAIN}/public_html/public"
fi
echo "Mode: $([ "$HAS_CYBERPANEL" = true ] && echo 'CyberPanel + OLS' || echo 'Standalone (nginx)')"

# ── PHP Binary ──
PHP_BIN=$(find /usr/local/lsws/ -maxdepth 3 -name php -path "*/bin/php" 2>/dev/null | sort -V | tail -1)
PHP_BIN="${PHP_BIN:-$(which php8.3 || which php)}"
echo "PHP: $($PHP_BIN -v | head -1)"

# ── Install stack (hanya jika TIDAK ada CyberPanel) ──
if [ "$HAS_CYBERPANEL" = false ]; then
  echo "⟳ Install nginx + PHP-FPM + MySQL..."
  apt update && apt upgrade -y
  apt install -y curl git mysql-server nginx \
    php8.3-fpm php8.3-mysql php8.3-gd php8.3-xml php8.3-mbstring \
    php8.3-zip php8.3-bcmath php8.3-intl php8.3-curl php8.3-opcache \
    unzip certbot python3-certbot-nginx supervisor

  # MySQL root: akses tanpa password
  mysql -u root -e "SELECT 1" >/dev/null 2>&1 || {
    echo "⚠️  MySQL perlu autentikasi — pastikan bisa: mysql -u root -e 'SELECT 1'"
    exit 1
  }
  echo "✔ Stack installed"
fi

# ── MySQL: buat DB + user ──
DBNAME="${DOMAIN//[^a-zA-Z0-9]/_}"
DBUSER="${DBNAME}"
DBPASS=$(openssl rand -hex 12)

echo "⟳ Buat DB ${DBNAME}..."
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
echo "✔ DB siap (user=${DBUSER})"

# ── Clone project ──
if [ "$HAS_CYBERPANEL" = true ]; then
  HOME_DIR="/home/${DOMAIN}"
  SITE_USER=$(stat -c '%U' "$HOME_DIR" 2>/dev/null || echo www-data)
  [ "$SITE_USER" = "root" ] && SITE_USER="lsadm"
else
  SITE_USER="www-data"
  mkdir -p "/home/${DOMAIN}"
fi

[ -d "$WEBROOT/.git" ] || {
  mkdir -p "$WEBROOT" 2>/dev/null
  git clone "$REPO" "$WEBROOT"
}
cd "$WEBROOT"

# ── Build ──
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm install --ignore-scripts --no-audit --no-fund 2>/dev/null && npm run build
echo "✔ Asset siap"

# ── .env ──
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

"$PHP_BIN" artisan key:generate --force

# ── Migrate + seed + cache ──
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force || true
"$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

# ── Permission ──
chown -R "${SITE_USER}:${SITE_USER}" "$WEBROOT"
chmod -R 755 "$WEBROOT"
chmod -R 775 "$WEBROOT/storage" "$WEBROOT/bootstrap/cache"
echo "✔ Permission"

# ── VHost (CyberPanel atau Nginx) ──
if [ "$HAS_CYBERPANEL" = true ]; then
  # CyberPanel: tulis vhost.conf OLS
  HANDLER=$(grep -oE '^extProcessor[[:space:]]+[A-Za-z0-9_]+' /usr/local/lsws/conf/httpd_config.conf 2>/dev/null | awk '{print $2}' | head -1 || echo lsphp)
  DOCROOT="${WEBROOT}"
  [ -d "/usr/local/lsws/conf/vhosts/${DOMAIN}" ] && {
    cat > "/usr/local/lsws/conf/vhosts/${DOMAIN}/vhost.conf" <<VH
docRoot                   ${DOCROOT}
vhDomain                  ${DOMAIN}
vhAliases                 www.${DOMAIN}
enableGzip                1
index  { useServer 0; indexFiles index.php index.html }
scripthandler { add lsapi:${HANDLER} php }
rewrite { enable 1; autoLoadHtaccess 1 }
context / { location ${DOCROOT}; allowBrowse 0; rewrite { enable 1; autoLoadHtaccess 1 }; addDefaultCharset off; phpIniOverride { } }
VH
    systemctl restart lsws 2>/dev/null
    echo "✔ OLS restart"
  }
else
  # Nginx
  cat > /etc/nginx/sites-available/ute-parts <<'NGX'
server {
  listen 80; listen [::]:80;
  server_name ${DOMAIN} www.${DOMAIN};
  root /var/www/ute-parts/public;
  index index.php;
  charset utf-8; client_max_body_size 64m; server_tokens off;
  location / { try_files $uri $uri/ /index.php?$query_string; }
  location ~ \.php$ { fastcgi_pass unix:/var/run/php/php8.3-fpm.sock; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; include fastcgi_params; }
  access_log /var/log/nginx/ute-parts.access.log; error_log /var/log/nginx/ute-parts.error.log;
}
NGX
  ln -sf /etc/nginx/sites-available/ute-parts /etc/nginx/sites-enabled/
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx
  certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN} 2>/dev/null || echo "⚠️  SSL gagal — set manual nanti"
  echo "✔ Nginx + SSL"
fi

# ── Supervisor (queue) ──
if command -v supervisorctl >/dev/null 2>&1; then
  cat > /etc/supervisor/conf.d/ute-parts.conf <<SPV
[program:ute-parts-queue]
command=${PHP_BIN} /var/www/ute-parts/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true; autorestart=true; user=${SITE_USER}
numprocs=1; stopwaitsecs=3600
stdout_logfile=/var/log/ute-parts-queue.log
SPV
  supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null
  echo "✔ Supervisor queue"
fi

# ── Auto-migrate (cron, untuk update dari git pull) ──
CRON="* * * * * cd ${WEBROOT} && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -v "schedule:run"; echo "$CRON") | crontab -

echo ""
echo "============================================================"
echo "  DEPLOY SELESAI"
echo "  URL: https://${DOMAIN}"
echo "  Login: /app/login → admin@uteparts.com / password"
echo "============================================================"
