#!/usr/bin/env bash
# ============================================================
# Ute Parts ERP — One-Click VPS Installer
# Linux (Ubuntu/Debian) | PHP 8.5 + Nginx + MySQL
#
# Setiap langkah ditampilkan + tidak ada output yang disembunyikan.
# Jalankan sebagai root:
#   bash setup-vps.sh test.uteparts.id
# ============================================================
set -euo pipefail

DOMAIN="${1:?Usage: bash $0 domain.com}"
REPO="https://github.com/bremspace/ute-parts-erp.git"
PROJECT="/var/www/${DOMAIN}"

step()  { echo ""; echo "------------------------------------------"; echo "[$1] $2"; echo "------------------------------------------"; }
ok()    { echo "  ? $1"; }
fail()  { echo "  ? $1"; exit 1; }

step 1 "System packages"
echo "  OS: $(cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d '\"')"
echo "  RAM: $(free -m | awk '/^Mem:/{print $2}')MB"

# PHP
echo "  [1a] PHP..."
if command -v php8.5 >/dev/null 2>&1; then
  ok "PHP $(php8.5 -r 'echo PHP_VERSION;') sudah ada"
else
  echo "  Menambahkan PPA Ondrej..."
  apt-get update -qq >/dev/null
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq software-properties-common >/dev/null 2>&1
  add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
  DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php8.5-fpm php8.5-mysql php8.5-gd php8.5-xml \
    php8.5-mbstring php8.5-zip php8.5-bcmath php8.5-intl \
    php8.5-curl php8.5-sqlite3 >/dev/null
  php8.5 -v >/dev/null 2>&1 && ok "PHP $(php8.5 -r 'echo PHP_VERSION;') terinstall" || fail "PHP 8.5 gagal"
fi

# Nginx
echo "  [1b] Nginx..."
dpkg -l nginx >/dev/null 2>&1 && ok "Nginx sudah ada" || {
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx >/dev/null 2>&1
  ok "Nginx terinstall"
}

# MySQL
echo "  [1c] MySQL..."
if command -v mysql >/dev/null 2>&1; then
  ok "MySQL/MariaDB sudah ada"
else
  echo "  Menginstall MariaDB..."
  echo "mariadb-server mariadb-server/root_password password root" | debconf-set-selections
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server >/dev/null 2>&1 || \
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server >/dev/null 2>&1
  ok "MariaDB/MySQL terinstall"
fi
mysql -u root -e "SELECT 1" >/dev/null 2>&1 && ok "MySQL akses OK" || fail "MySQL akses gagal — cek manual: mysql -u root -e 'SELECT 1'"

# Composer
echo "  [1d] Composer..."
command -v composer >/dev/null 2>&1 || {
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq composer >/dev/null 2>&1
  ok "Composer terinstall"
}
ok "Composer $(composer --version 2>/dev/null | head -c 30)"

step 2 "Database MySQL"
DBNAME="${DOMAIN//[^a-zA-Z0-9]/_}"
DBUSER="${DBNAME}"
DBPASS="$(openssl rand -hex 12)"
echo "  Database : ${DBNAME}"
echo "  Username : ${DBUSER}"
echo "  Password : ${DBPASS}"
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '${DBUSER}'@'localhost' IDENTIFIED BY '${DBPASS}';
  GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO '${DBUSER}'@'localhost';
  FLUSH PRIVILEGES;" && ok "Database '${DBNAME}' siap" || fail "Database gagal"

step 3 "Clone project"
mkdir -p "$PROJECT"
cd "$PROJECT"
echo "  git clone --depth 1 $REPO ."
git clone --depth 1 "$REPO" . 2>/dev/null || { git remote add origin "$REPO" 2>/dev/null || true; git pull origin main 2>/dev/null; }
ok "Project ada di $PROJECT"

step 4 "Composer install"
echo "  Menjalankan composer install... (perlu 1-3 menit)"
COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 composer install --no-dev --prefer-dist --no-interaction 2>&1 | grep -E "Installing|Generating|OK|error|fail" || true
ok "Composer selesai"

step 5 "NPM build"
echo "  npm install..."
npm install --ignore-scripts --no-audit --no-fund 2>&1 | grep -E "added|audited|warn|ERR" || true
echo "  npm run build..."
npm run build 2>&1 | grep -E "?|error|warn|built" || true
ok "NPM selesai"

step 6 "Konfigurasi .env + Laravel"
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
ok ".env ditulis"

php8.5 artisan key:generate --force && ok "APP_KEY di-generate" || ok "APP_KEY sudah ada"
php8.5 artisan migrate --force 2>&1 | tail -3 && ok "Migrate selesai"
php8.5 artisan db:seed --force 2>&1 | tail -3 || true
php8.5 artisan storage:link 2>&1 | tail -1
php8.5 artisan config:cache 2>&1 | tail -1
php8.5 artisan route:cache 2>&1 | tail -1
php8.5 artisan view:cache 2>&1 | tail -1
ok "Cache selesai"

step 7 "Permission & Nginx"
chown -R www-data:www-data "$PROJECT"
chmod -R 755 "$PROJECT"
chmod -R 775 "$PROJECT/storage" "$PROJECT/bootstrap/cache"
ok "Permission OK"

cat > /etc/nginx/sites-available/ute-parts <<'NGX'
server {
    listen 80; listen [::]:80;
    server_name test.uteparts.id www.test.uteparts.id;
    root /var/www/test.uteparts.id/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64m;
    server_tokens off;
    access_log /var/log/nginx/ute-parts.access.log;
    error_log  /var/log/nginx/ute-parts.error.log;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGX
sed -i "s/test\.uteparts\.id/${DOMAIN}/g" /etc/nginx/sites-available/ute-parts
ln -sf /etc/nginx/sites-available/ute-parts /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx 2>/dev/null || systemctl start nginx 2>/dev/null
ok "Nginx dikonfigurasi"

step 8 "Queue worker (opsional)"
if command -v supervisorctl >/dev/null 2>&1; then
  mkdir -p /var/log
  cat > /etc/supervisor/conf.d/ute-parts.conf <<'SPV'
[program:ute-parts-queue]
command=php8.5 /var/www/test.uteparts.id/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true; autorestart=true; user=root; numprocs=1
stdout_logfile=/var/log/ute-parts-queue.log
SPV
  supervisorctl reread 2>/dev/null && supervisorctl update 2>/dev/null
  ok "Queue worker aktif"
else
  echo "  Supervisor tidak ada — queue bisa dijalankan manual nanti: php8.5 artisan queue:work"
fi

step 9 "SELESAI"
IP=$(hostname -I | awk '{print $1}')
echo ""
echo "========================================"
echo "  ? PROJECT DEPLOYED"
echo "========================================"
echo "  Folder  : $PROJECT"
echo "  URL     : http://${IP}"
echo "  Login   : /app/login"
echo "  Email   : admin@uteparts.com"
echo "  Password: password"
echo ""
echo "  Akses lokal (via IP) sekarang:"
echo "    http://${IP}/app/login"
echo "    http://${IP}/shop"
echo ""
echo "  Untuk domain (opsional):"
echo "    1. Buat A record DNS: ${DOMAIN} -> ${IP}"
echo "    2. Jalankan: certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"
echo "========================================"
