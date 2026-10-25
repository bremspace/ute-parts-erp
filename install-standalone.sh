#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP & Marketplace — One-Click Standalone Production Installer
# Untuk: Fresh VPS / Dedicated Server / Local Server (Ubuntu 22.04 / 24.04 LTS)
# Stack: Nginx + PHP 8.4-FPM + MySQL + Supervisor Queue Worker + Node.js 20
#
# Penggunaan:
#   sudo bash install-standalone.sh yourdomain.com
#   atau (jika pakai IP Publik / localhost):
#   sudo bash install-standalone.sh 103.xxx.xxx.xxx
# ==============================================================================
set -euo pipefail

# 1. Validasi Root & Argumen
if [ "$(id -u)" -ne 0 ]; then
  echo "❌ Error: Skrip ini wajib dijalankan sebagai root (gunakan: sudo bash $0 domain.com)"
  exit 1
fi

DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
  echo "Penggunaan: sudo bash $0 <domain-atau-ip>"
  echo "Contoh:     sudo bash $0 erp.uteparts.id"
  exit 1
fi

WEBROOT="/var/www/uteparts"
DOCROOT="${WEBROOT}/public"
REPO_GIT="https://github.com/bremspace/ute-parts-erp.git"
DB_NAME="uteparts_db"
DB_USER="uteparts_user"
DB_PASS="$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 16)"

echo "======================================================================"
echo "🚀 Memulai Instalasi Otomatis Ute Parts ERP di: $DOMAIN"
echo "======================================================================"

# 2. Swap 2GB jika RAM < 3GB (Proteksi OOM saat build)
TOTAL_RAM_KB=$(grep MemTotal /proc/meminfo | awk '{print $2}')
if [ "$TOTAL_RAM_KB" -lt 3145728 ]; then
  if [ ! -f /swapfile ]; then
    echo "⟳ Membuat Swap 2GB (Safeguard OOM VPS)..."
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo "✔ Swap 2GB berhasil diaktifkan."
  fi
fi

# 3. Update Paket & Repositori PHP Sury
echo "⟳ Memperbarui paket sistem & repository PHP 8.4..."
apt-get update -y
apt-get install -y software-properties-common curl git unzip ca-certificates lsb-release

if ! grep -q "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
  add-apt-repository ppa:ondrej/php -y
  apt-get update -y
fi

# 4. Instalasi Nginx, MySQL, PHP 8.4-FPM, Supervisor & Tools
echo "⟳ Menginstal Nginx, MySQL, PHP 8.4-FPM, GD/Imagick, dan Supervisor..."
apt-get install -y \
  nginx mysql-server \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-sqlite3 \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd \
  php8.4-bcmath php8.4-intl php8.4-opcache php8.4-imagick \
  imagemagick libheif-examples supervisor certbot python3-certbot-nginx

# 5. Instalasi Composer
if ! command -v composer >/dev/null 2>&1; then
  echo "⟳ Menginstal Composer..."
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# 6. Instalasi Node.js 20 LTS (jika belum ada)
if ! command -v node >/dev/null 2>&1; then
  echo "⟳ Menginstal Node.js 20 LTS..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

# 7. Setup Database MySQL
echo "⟳ Mengonfigurasi Database MySQL ($DB_NAME)..."
systemctl start mysql
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
echo "✔ Database $DB_NAME siap."

# 8. Clone Source Code
if [ ! -d "$WEBROOT/.git" ]; then
  echo "⟳ Mengunduh repositori ke $WEBROOT..."
  mkdir -p "$WEBROOT"
  git clone "$REPO_GIT" "$WEBROOT"
else
  echo "✔ Repositori sudah ada di $WEBROOT, memperbarui..."
  cd "$WEBROOT"
  git pull origin main || true
fi

cd "$WEBROOT"
export COMPOSER_ALLOW_SUPERUSER=1

# 9. Composer Install & Build Frontend
echo "⟳ Menjalankan composer install (--no-dev)..."
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

echo "⟳ Menjalankan npm install & build asset..."
npm install --ignore-scripts --no-audit --no-fund
npm run build

# 10. Konfigurasi .env
echo "⟳ Menyiapkan file .env produksi..."
if [ ! -f .env ]; then
  cp .env.example .env
fi

PROTOCOL="http"
if [[ "$DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || [[ "$DOMAIN" == "localhost" ]]; then
  APP_URL="http://${DOMAIN}"
else
  APP_URL="https://${DOMAIN}"
  PROTOCOL="https"
fi

sed -i "s|^APP_NAME=.*|APP_NAME=\"Ute Parts\"|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=database|" .env

GIT_SECRET="$(openssl rand -hex 16)"
grep -q "GIT_DEPLOY_SECRET" .env && sed -i "s|^GIT_DEPLOY_SECRET=.*|GIT_DEPLOY_SECRET=${GIT_SECRET}|" .env || echo "GIT_DEPLOY_SECRET=${GIT_SECRET}" >> .env
grep -q "GIT_DEPLOY_BRANCH" .env || echo "GIT_DEPLOY_BRANCH=main" >> .env

php artisan key:generate --force
php artisan migrate --force --seed
php artisan storage:link

# 11. Hak Akses File (www-data)
echo "⟳ Mengatur hak akses file storage & cache..."
chown -R www-data:www-data "$WEBROOT"
find "$WEBROOT" -type d -exec chmod 755 {} \;
find "$WEBROOT" -type f -exec chmod 644 {} \;
chmod -R 775 "${WEBROOT}/storage" "${WEBROOT}/bootstrap/cache"

# 12. Cache Warmup
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 13. Konfigurasi Nginx Server Block
echo "⟳ Mengonfigurasi Nginx Web Server..."
NGINX_CONF="/etc/nginx/sites-available/uteparts.conf"
cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${DOCROOT};

    index index.php index.html;
    charset utf-8;

    client_max_body_size 64M;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# 14. Konfigurasi Supervisor Queue Worker
echo "⟳ Mengonfigurasi Supervisor Worker..."
SUPERVISOR_CONF="/etc/supervisor/conf.d/uteparts-worker.conf"
cat > "$SUPERVISOR_CONF" <<EOF
[program:uteparts-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php ${WEBROOT}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --memory=128
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/uteparts-worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
supervisorctl start uteparts-worker:* || true

# 15. Konfigurasi Cron Scheduler
echo "⟳ Mengonfigurasi Crontab Scheduler Laravel..."
CRON_FILE="/etc/cron.d/uteparts"
cat > "$CRON_FILE" <<EOF
* * * * * www-data /usr/bin/php ${WEBROOT}/artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 "$CRON_FILE"

# 16. Pasang AI Development & Maintenance Stack (OpenCode + 9router + MCP)
if [ -f "${WEBROOT}/deploy/setup-ai-dev-stack.sh" ]; then
  echo ""
  echo "⟳ Menyiapkan AI Development & Maintenance Stack (OpenCode, 9router, MCP)..."
  bash "${WEBROOT}/deploy/setup-ai-dev-stack.sh" || {
    echo "⚠️ Setup AI stack opsional gagal sebagian; sistem aplikasi utama tetap berjalan normal."
  }
fi

# 17. Opsi Pasang SSL Gratis (Certbot) jika FQDN domain
if [[ ! "$DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && [ "$DOMAIN" != "localhost" ]; then
  echo ""
  echo "⟳ Mendaftarkan SSL Let's Encrypt Otomatis via Certbot..."
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "admin@${DOMAIN}" --redirect || {
    echo "⚠️ Certbot dilewati atau gagal verifikasi DNS. Anda dapat menjalankannya nanti dengan: certbot --nginx -d $DOMAIN"
  }
fi

echo ""
echo "======================================================================"
echo "🎉 INSTALASI SELESAI & SISTEM SIAP DIGUNAKAN!"
echo "======================================================================"
echo "🌐 URL Aplikasi     : ${PROTOCOL}://${DOMAIN}/"
echo "🔐 Login Backoffice  : ${PROTOCOL}://${DOMAIN}/app/login"
echo "👤 Akun Super Admin  : superadmin@uteparts.id"
echo "🔑 Password Default  : Password123!"
echo ""
echo "📦 Data Database Terbuat:"
echo "   Database : ${DB_NAME}"
echo "   Username : ${DB_USER}"
echo "   Password : ${DB_PASS}"
echo ""
echo "🔄 Git CI/CD Webhook: ${PROTOCOL}://${DOMAIN}/webhook/git-deploy"
echo "🤖 AI Dev Node      : OpenCode CLI & 9Router aktif di port 20128"
echo "======================================================================"
