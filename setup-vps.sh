#!/usr/bin/env bash
set -euo pipefail
DOMAIN="${1:?Usage: bash $0 domain.com}"
REPO="https://github.com/bremspace/ute-parts-erp.git"
WEBROOT="/home/${DOMAIN}/public_html"
DOCROOT="${WEBROOT}/public"

PHP_BIN=$(find /usr/local/lsws/ -maxdepth 3 -name php -path "*/bin/php" 2>/dev/null | sort -V | tail -1)
PHP_BIN="${PHP_BIN:-php}"
echo "PHP: $($PHP_BIN -v | head -1)"

HANDLER=$(grep -oE '^extProcessor[[:space:]]+[A-Za-z0-9_]+' /usr/local/lsws/conf/httpd_config.conf 2>/dev/null | awk '{print $2}' | head -1 || echo lsphp)
echo "Handler: lsapi:${HANDLER}"

[ -d "$HOME_DIR" ] 2>/dev/null || { echo "Folder /home/$DOMAIN tidak ada - buat website dulu di CyberPanel."; exit 1; }
HOME_DIR="/home/${DOMAIN}"
SITE_USER=$(stat -c '%U' "$HOME_DIR")
[ "$SITE_USER" = "root" ] && SITE_USER="lsadm"
echo "Site user: $SITE_USER"

if [ -d "$WEBROOT/.git" ]; then
  echo "Project ada - pull..."
  cd "$WEBROOT" && git pull origin main || { git fetch && git reset --hard origin/main; }
else
  mkdir -p "$WEBROOT"
  [ -n "$(ls -A $WEBROOT 2>/dev/null)" ] && { cp -a "$WEBROOT" "${HOME_DIR}/_bak" 2>/dev/null; rm -rf "$WEBROOT"/*; }
  echo "Clone..."
  git clone "$REPO" "$WEBROOT"
fi
cd "$WEBROOT"

export COMPOSER_ALLOW_SUPERUSER=1
echo "composer install..."
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm install --ignore-scripts --no-audit --no-fund 2>/dev/null && npm run build
echo "Asset siap"

DBNAME="${DOMAIN//[^a-zA-Z0-9]/_}"
DBUSER="${DBNAME}"
DBPASS=$(openssl rand -hex 12)
echo "Bu DB ${DBNAME}..."

mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}'; GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}'; GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null
echo "DB OK user=${DBUSER} pass=${DBPASS}"

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
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force || true
"$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

chown -R "${SITE_USER}:${SITE_USER}" "$WEBROOT"
chmod -R 755 "$WEBROOT"
chmod -R 775 "$WEBROOT/storage" "$WEBROOT/bootstrap/cache"
chmod o+rx /home "$HOME_DIR" 2>/dev/null || true
mkdir -p "${HOME_DIR}/logs"
chown -R "${SITE_USER}:${SITE_USER}" "${HOME_DIR}/logs"
echo "Permission OK"

if [ -d "/usr/local/lsws/conf/vhosts/${DOMAIN}" ]; then
  cat > "/usr/local/lsws/conf/vhosts/${DOMAIN}/vhost.conf" <<VH
docRoot                   ${DOCROOT}
vhDomain                  ${DOMAIN}
vhAliases                 www.${DOMAIN}
enableGzip                1

index  {
  useServer               0
  indexFiles              index.php index.html
}

scripthandler {
  add                     lsapi:${HANDLER} php
}

rewrite {
  enable                  1
  autoLoadHtaccess        1
}

context / {
  location                ${DOCROOT}
  allowBrowse             0
  rewrite {
    enable                1
    autoLoadHtaccess      1
  }
  addDefaultCharset       off
  phpIniOverride {
  }
}
VH
  systemctl restart lsws 2>/dev/null
  echo "OLS vhost + restart OK"
fi

echo ""
echo "DEPLOY SELESAI: https://${DOMAIN}"
echo "Login: /app/login -> admin@uteparts.com / password"
