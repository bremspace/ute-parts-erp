#!/usr/bin/env bash
# ==============================================================================
# ERP & POS SYSTEM — STANDALONE INTERACTIVE VPS INSTALLER (TUI)
# Platform: Ubuntu 22.04 / 24.04 LTS (Fresh / Blank VPS or Existing Server)
# Stack   : Nginx + PHP 8.4-FPM + MySQL 8.0 + Supervisor + Composer + Node.js
# ==============================================================================
set -euo pipefail

# ANSI Color Codes
C_RESET="\033[0m"
C_BOLD="\033[1m"
C_DIM="\033[2m"
C_RED="\033[1;31m"
C_GREEN="\033[1;32m"
C_YELLOW="\033[1;33m"
C_BLUE="\033[1;34m"
C_MAGENTA="\033[1;35m"
C_CYAN="\033[1;36m"
C_WHITE="\033[1;37m"
C_BG_BLUE="\033[44m"

# Header UI Helper
clear 2>/dev/null || true
echo -e "${C_CYAN}╔══════════════════════════════════════════════════════════════════════════════╗${C_RESET}"
echo -e "${C_CYAN}║${C_RESET}      ${C_BOLD}${C_WHITE}ERP & POS SYSTEM — STANDALONE INTERACTIVE VPS INSTALLER${C_RESET}      ${C_CYAN}║${C_RESET}"
echo -e "${C_CYAN}║${C_RESET}   ${C_DIM}Instalasi Otomatis Universal untuk Fresh / Blank VPS Ubuntu Linux${C_RESET}        ${C_CYAN}║${C_RESET}"
echo -e "${C_CYAN}╚══════════════════════════════════════════════════════════════════════════════╝${C_RESET}"
echo ""

# 1. Validasi Akses Root
if [ "$(id -u)" -ne 0 ]; then
    echo -e "${C_RED}[ERROR] Skrip ini membutuhkan hak akses root.${C_RESET}"
    echo -e "Silakan jalankan ulang dengan perintah: ${C_YELLOW}sudo bash $0${C_RESET}"
    exit 1
fi

# 2. Audit Spesifikasi Perangkat Keras & IP
echo -e "${C_BOLD}${C_CYAN}⟳ Memeriksa Spesifikasi VPS...${C_RESET}"
TOTAL_RAM_KB=$(grep MemTotal /proc/meminfo | awk '{print $2}')
TOTAL_RAM_MB=$((TOTAL_RAM_KB / 1024))
CPU_CORES=$(nproc)
DISK_FREE_GB=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
OS_NAME=$(grep PRETTY_NAME /etc/os-release | cut -d= -f2 | tr -d '\"' || echo "Ubuntu Linux")

DETECTED_IP=""
if command -v curl >/dev/null 2>&1; then
    DETECTED_IP=$(curl -4 -s --max-time 3 https://api.ipify.org || curl -4 -s --max-time 3 https://ifconfig.me || true)
fi
if [ -z "$DETECTED_IP" ]; then
    DETECTED_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1")
fi

echo -e "  • ${C_WHITE}OS${C_RESET}         : ${C_GREEN}${OS_NAME}${C_RESET}"
echo -e "  • ${C_WHITE}CPU Cores${C_RESET}  : ${C_GREEN}${CPU_CORES} Core${C_RESET}"
echo -e "  • ${C_WHITE}RAM Fisik${C_RESET}  : ${C_GREEN}${TOTAL_RAM_MB} MB${C_RESET}"
echo -e "  • ${C_WHITE}Sisa Disk${C_RESET}  : ${C_GREEN}${DISK_FREE_GB} GB Tersedia${C_RESET}"
echo -e "  • ${C_WHITE}IP Publik${C_RESET}  : ${C_YELLOW}${DETECTED_IP}${C_RESET}"
echo ""

# 3. INTERACTIVE TUI WIZARD
echo -e "${C_BOLD}${C_MAGENTA}┌──────────────────────────────────────────────────────────────────────────────┐${C_RESET}"
echo -e "${C_BOLD}${C_MAGENTA}│                    WIZARD KONFIGURASI SISTEM VPS (TUI)                      │${C_RESET}"
echo -e "${C_BOLD}${C_MAGENTA}└──────────────────────────────────────────────────────────────────────────────┘${C_RESET}"
echo -e "${C_DIM}Tekan [Enter] untuk langsung menggunakan nilai default yang ada dalam kurung [ ].${C_RESET}"
echo ""

# Step 1: Domain / Hostname
echo -e "${C_BOLD}${C_WHITE}[1/6] Domain Web atau IP Publik${C_RESET}"
echo -e "      ${C_DIM}Contoh: erp.tokoanda.com atau gunakan IP jika belum ada domain.${C_RESET}"
read -rp "      Domain / IP [$DETECTED_IP]: " INPUT_DOMAIN
DOMAIN="${INPUT_DOMAIN:-$DETECTED_IP}"
DOMAIN="$(echo "$DOMAIN" | tr -d ' ' | tr '[:upper:]' '[:lower:]')"

# Step 2: Nama Aplikasi & Mode
echo ""
echo -e "${C_BOLD}${C_WHITE}[2/6] Nama Toko / Usaha${C_RESET}"
read -rp "      Nama Aplikasi [ERP & POS System]: " INPUT_APP_NAME
APP_NAME="${INPUT_APP_NAME:-ERP & POS System}"

echo ""
echo -e "${C_BOLD}${C_WHITE}[3/6] Mode Fitur Aplikasi${C_RESET}"
echo -e "      1) Full ERP + POS + Marketplace Katalog Konsumen (Rekomendasi)"
echo -e "      2) Backoffice & POS Kasir Saja"
read -rp "      Pilihan Mode [1]: " INPUT_MODE
if [ "$INPUT_MODE" = "2" ]; then
    APP_ROLE="erp"
else
    APP_ROLE="full"
fi

# Step 3: Konfigurasi Database
echo ""
DEFAULT_DB="uteparts_db"
DEFAULT_USER="uteparts_user"
DEFAULT_PASS=$(openssl rand -hex 8 2>/dev/null || date +%s%N | head -c 16)

echo -e "${C_BOLD}${C_WHITE}[4/6] Konfigurasi Database MySQL (Terisolasi 127.0.0.1)${C_RESET}"
read -rp "      Nama Database [$DEFAULT_DB]: " INPUT_DB_NAME
DB_NAME="${INPUT_DB_NAME:-$DEFAULT_DB}"
read -rp "      Username Database [$DEFAULT_USER]: " INPUT_DB_USER
DB_USER="${INPUT_DB_USER:-$DEFAULT_USER}"
read -rp "      Password Database [$DEFAULT_PASS]: " INPUT_DB_PASS
DB_PASS="${INPUT_DB_PASS:-$DEFAULT_PASS}"

# Step 4: Akun Super Administrator
echo ""
DEFAULT_ADMIN_EMAIL="admin@${DOMAIN}"
if [[ "$DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || [ "$DOMAIN" = "localhost" ]; then
    DEFAULT_ADMIN_EMAIL="admin@uteparts.id"
fi
DEFAULT_ADMIN_PASS="Admin123!#"

echo -e "${C_BOLD}${C_WHITE}[5/6] Akun Super Administrator (Login Awal Backoffice)${C_RESET}"
read -rp "      Nama Administrator [Administrator]: " INPUT_ADMIN_NAME
ADMIN_NAME="${INPUT_ADMIN_NAME:-Administrator}"
read -rp "      Email Login Admin [$DEFAULT_ADMIN_EMAIL]: " INPUT_ADMIN_EMAIL
ADMIN_EMAIL="${INPUT_ADMIN_EMAIL:-$DEFAULT_ADMIN_EMAIL}"
read -rp "      Password Login Admin [$DEFAULT_ADMIN_PASS]: " INPUT_ADMIN_PASS
ADMIN_PASS="${INPUT_ADMIN_PASS:-$DEFAULT_ADMIN_PASS}"

# Step 5: SSL & Integrasi Opsional
IS_FQDN=0
if [[ ! "$DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && [ "$DOMAIN" != "localhost" ]; then
    IS_FQDN=1
fi

ENABLE_SSL="n"
if [ "$IS_FQDN" -eq 1 ]; then
    echo ""
    echo -e "${C_BOLD}${C_WHITE}[6/6] Sertifikat SSL Let's Encrypt (HTTPS)${C_RESET}"
    echo -e "      ${C_DIM}Pastikan DNS A Record domain ${DOMAIN} telah mengarah ke IP ${DETECTED_IP}.${C_RESET}"
    read -rp "      Aktifkan HTTPS Gratis otomatis via Certbot? (y/N) [y]: " INPUT_SSL
    INPUT_SSL="${INPUT_SSL:-y}"
    if [[ "$INPUT_SSL" =~ ^[Yy]$ ]]; then
        ENABLE_SSL="y"
    fi
fi

# Ringkasan Sebelum Eksekusi
echo ""
echo -e "${C_BOLD}${C_YELLOW}╔══════════════════════════════════════════════════════════════════════════════╗${C_RESET}"
echo -e "${C_BOLD}${C_YELLOW}║                        RINGKASAN INSTALASI VPS                               ║${C_RESET}"
echo -e "${C_BOLD}${C_YELLOW}╠══════════════════════════════════════════════════════════════════════════════╣${C_RESET}"
echo -e "  • ${C_WHITE}Domain / Host${C_RESET}     : ${C_CYAN}${DOMAIN}${C_RESET}"
echo -e "  • ${C_WHITE}Nama Aplikasi${C_RESET}     : ${C_CYAN}${APP_NAME}${C_RESET}"
echo -e "  • ${C_WHITE}Mode Fitur${C_RESET}        : ${C_CYAN}${APP_ROLE}${C_RESET}"
echo -e "  • ${C_WHITE}Database${C_RESET}          : ${C_CYAN}${DB_NAME}${C_RESET} (User: ${DB_USER})"
echo -e "  • ${C_WHITE}Super Admin${C_RESET}       : ${C_CYAN}${ADMIN_EMAIL}${C_RESET}"
echo -e "  • ${C_WHITE}Pasang SSL HTTPS${C_RESET}  : ${C_CYAN}${ENABLE_SSL}${C_RESET}"
echo -e "${C_BOLD}${C_YELLOW}╚══════════════════════════════════════════════════════════════════════════════╝${C_RESET}"
echo ""

read -rp "Mulai instalasi otomatis sekarang? (Y/n) [Y]: " CONFIRM_INSTALL
CONFIRM_INSTALL="${CONFIRM_INSTALL:-Y}"
if [[ ! "$CONFIRM_INSTALL" =~ ^[Yy]$ ]]; then
    echo -e "${C_YELLOW}[DIBATALKAN] Proses instalasi dihentikan oleh pengguna.${C_RESET}"
    exit 0
fi

echo ""
echo -e "${C_BOLD}${C_GREEN}════════════════════════════════════════════════════════════════════════════════${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}🚀 MEMULAI PROSES INSTALASI STANDALONE VPS...                                  ${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}════════════════════════════════════════════════════════════════════════════════${C_RESET}"

# STEP 1: Safeguard Memori (Swap 2GB jika RAM < 3GB)
if [ "$TOTAL_RAM_MB" -lt 3000 ]; then
    if [ ! -f /swapfile ] && [ "$(swapon --show | wc -l)" -le 1 ]; then
        echo -e "${C_CYAN}⟳ [1/10] Membuat Swapfile 2GB untuk stabilitas memori (Anti-OOM)...${C_RESET}"
        fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
        swapon /swapfile >/dev/null
        if ! grep -q "/swapfile" /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        echo -e "${C_GREEN}✔ Swap 2GB aktif.${C_RESET}"
    else
        echo -e "${C_GREEN}✔ [1/10] Kapasitas memori/swap sudah mencukupi.${C_RESET}"
    fi
else
    echo -e "${C_GREEN}✔ [1/10] Kapasitas RAM mencukupi (${TOTAL_RAM_MB} MB).${C_RESET}"
fi

# STEP 2: Pasang Paket Sistem & Repositori PHP 8.4
echo -e "${C_CYAN}⟳ [2/10] Memperbarui repositori sistem & dependensi dasar...${C_RESET}"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq >/dev/null
apt-get install -y -qq software-properties-common curl git unzip ca-certificates lsb-release ufw fail2ban >/dev/null

if ! grep -q "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
    echo -e "${C_CYAN}⟳ Menambahkan PPA PHP Ondrej Sury resmi...${C_RESET}"
    add-apt-repository ppa:ondrej/php -y >/dev/null 2>&1 || true
    apt-get update -qq >/dev/null
fi

# Deteksi versi PHP terbaik (Gunakan 8.4 jika tersedia, fallback 8.3)
PHP_TARGET="8.4"
if ! apt-cache show php8.4-fpm >/dev/null 2>&1; then
    PHP_TARGET="8.3"
fi

echo -e "${C_CYAN}⟳ Menginstal Nginx, MySQL, PHP ${PHP_TARGET}, GD, Imagick, dan Supervisor...${C_RESET}"
apt-get install -y -qq \
    nginx mysql-server supervisor certbot python3-certbot-nginx \
    "php${PHP_TARGET}-fpm" "php${PHP_TARGET}-cli" "php${PHP_TARGET}-mysql" "php${PHP_TARGET}-sqlite3" \
    "php${PHP_TARGET}-mbstring" "php${PHP_TARGET}-xml" "php${PHP_TARGET}-curl" "php${PHP_TARGET}-zip" \
    "php${PHP_TARGET}-gd" "php${PHP_TARGET}-bcmath" "php${PHP_TARGET}-intl" "php${PHP_TARGET}-opcache" \
    "php${PHP_TARGET}-imagick" >/dev/null

PHP_BIN="/usr/bin/php${PHP_TARGET}"
if [ ! -x "$PHP_BIN" ]; then
    PHP_BIN="$(which php)"
fi

# STEP 3: Pastikan Composer & Node.js Tersedia
echo -e "${C_CYAN}⟳ [3/10] Memeriksa Composer dan Node.js...${C_RESET}"
export COMPOSER_ALLOW_SUPERUSER=1

if ! command -v composer >/dev/null 2>&1; then
    echo -e "${C_CYAN}  • Menginstal Composer resmi...${C_RESET}"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>/dev/null || true
    if [ -f composer-setup.php ]; then
        "$PHP_BIN" composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet 2>/dev/null || true
        rm -f composer-setup.php
    fi
    if ! command -v composer >/dev/null 2>&1; then
        apt-get install -y -qq composer >/dev/null 2>&1 || true
    fi
fi

if ! command -v node >/dev/null 2>&1; then
    echo -e "${C_CYAN}  • Menginstal Node.js & NPM via apt...${C_RESET}"
    apt-get install -y -qq nodejs npm >/dev/null 2>&1 || true
fi

NODE_VER=$(node -v 2>/dev/null || echo 'N/A')
COMPOSER_VER=$($PHP_BIN $(which composer) --version 2>/dev/null | grep -o 'Composer version [^ ]*' || echo 'Composer Ready')
echo -e "${C_GREEN}✔ PHP: $($PHP_BIN -r 'echo PHP_VERSION;') | ${COMPOSER_VER} | Node: ${NODE_VER}${C_RESET}"

# STEP 4: Keamanan Database MySQL & Inisialisasi Skema
echo -e "${C_CYAN}⟳ [4/10] Mengamankan & Menyiapkan Database MySQL ($DB_NAME)...${C_RESET}"
systemctl start mysql 2>/dev/null || service mysql start 2>/dev/null || true

# Kunci bind-address MySQL ke 127.0.0.1 agar tidak bocor ke publik
for mycnf in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mariadb.conf.d/50-server.cnf /etc/mysql/my.cnf; do
    if [ -f "$mycnf" ] && grep -q "0.0.0.0" "$mycnf" 2>/dev/null; then
        sed -i 's/bind-address\s*=\s*0.0.0.0/bind-address = 127.0.0.1/g' "$mycnf" 2>/dev/null || true
        systemctl restart mysql 2>/dev/null || true
    fi
done

# Buat Database dan User terisolasi (localhost & 127.0.0.1 saja) via socket/sudo
MYSQL_CMD="mysql"
if command -v mariadb >/dev/null 2>&1; then
    MYSQL_CMD="mariadb"
fi

$MYSQL_CMD -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
" 2>/dev/null || $MYSQL_CMD -u root -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
" 2>/dev/null || true
echo -e "${C_GREEN}✔ Database $DB_NAME aman & siap digunakan.${C_RESET}"

# STEP 5: Lokasi Webroot & Source Code
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="/var/www/uteparts"

# Jika script dijalankan langsung dari dalam repositori project yang sudah ada
if [ -f "${SCRIPT_DIR}/artisan" ] && [ -f "${SCRIPT_DIR}/composer.json" ]; then
    TARGET_DIR="$SCRIPT_DIR"
    echo -e "${C_GREEN}✔ [5/10] Menggunakan direktori project aktif saat ini: ${TARGET_DIR}${C_RESET}"
else
    echo -e "${C_CYAN}⟳ [5/10] Menyiapkan source code di ${TARGET_DIR}...${C_RESET}"
    mkdir -p "$TARGET_DIR"
    if [ ! -d "${TARGET_DIR}/.git" ]; then
        git clone https://github.com/bremspace/ute-parts-erp.git "$TARGET_DIR"
    else
        cd "$TARGET_DIR"
        git pull origin main || true
    fi
fi

cd "$TARGET_DIR"
export COMPOSER_ALLOW_SUPERUSER=1

# STEP 6: Composer Install & Build Frontend Vite
echo -e "${C_CYAN}⟳ [6/10] Menjalankan composer install (--optimize-autoloader)...${C_RESET}"
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

echo -e "${C_CYAN}⟳ Menyiapkan aset antarmuka Vite frontend...${C_RESET}"
if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    echo -e "${C_GREEN}✔ Aset produksi frontend sudah tersedia (pre-built).${C_RESET}"
else
    echo -e "${C_CYAN}  • Mengompilasi aset via npm (mungkin butuh 1-2 menit)...${C_RESET}"
    npm install --no-audit --no-fund --ignore-scripts
    npm run build
fi
echo -e "${C_GREEN}✔ Composer & aset frontend siap.${C_RESET}"

# STEP 7: Konfigurasi File .env
echo -e "${C_CYAN}⟳ [7/10] Mengonfigurasi parameter .env...${C_RESET}"
if [ ! -f .env ]; then
    cp .env.example .env
fi

APP_PROTOCOL="http"
if [ "$ENABLE_SSL" = "y" ]; then
    APP_PROTOCOL="https"
fi
APP_URL="${APP_PROTOCOL}://${DOMAIN}"

# Tulis konfigurasi utama
sed -i "s|^APP_NAME=.*|APP_NAME=\"${APP_NAME}\"|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
sed -i "s|^APP_ROLE=.*|APP_ROLE=${APP_ROLE}|" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|" .env
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=file|" .env

GIT_SECRET="$(openssl rand -hex 16 2>/dev/null || date +%s)"
if grep -q "^GIT_DEPLOY_SECRET=" .env; then
    sed -i "s|^GIT_DEPLOY_SECRET=.*|GIT_DEPLOY_SECRET=${GIT_SECRET}|" .env
else
    echo "GIT_DEPLOY_SECRET=${GIT_SECRET}" >> .env
fi

# STEP 8: Migrasi Database, Storage Link & Setup Super Admin
echo -e "${C_CYAN}⟳ [8/10] Menjalankan migrasi database & mendaftarkan Super Admin...${C_RESET}"
"$PHP_BIN" artisan key:generate --force >/dev/null 2>&1 || true
"$PHP_BIN" artisan migrate --force --seed >/dev/null 2>&1 || true
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# Daftarkan akun Admin via Tinker
"$PHP_BIN" artisan tinker --execute="
try {
    \$user = \App\Models\User::updateOrCreate(
        ['email' => '${ADMIN_EMAIL}'],
        [
            'name' => '${ADMIN_NAME}',
            'password' => \Illuminate\Support\Facades\Hash::make('${ADMIN_PASS}'),
            'is_active' => true,
        ]
    );
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::findOrCreate('super-admin');
        \$user->syncRoles(['super-admin']);
    }
} catch (\Throwable \$e) {}
" >/dev/null 2>&1 || true

# Cache Warmup
"$PHP_BIN" artisan config:cache >/dev/null 2>&1 || true
"$PHP_BIN" artisan route:cache >/dev/null 2>&1 || true
"$PHP_BIN" artisan view:cache >/dev/null 2>&1 || true

# Hak Akses Direktori
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$TARGET_DIR" -type f -exec chmod 644 {} \; 2>/dev/null || true
chmod -R 775 "${TARGET_DIR}/storage" "${TARGET_DIR}/bootstrap/cache" 2>/dev/null || true
chmod +x "${TARGET_DIR}/artisan" "${TARGET_DIR}/start-install.sh" "${TARGET_DIR}/start-install-vps.sh" 2>/dev/null || true
touch "${TARGET_DIR}/storage/installed.lock"

# STEP 9: Konfigurasi Nginx Server Block & FastCGI
echo -e "${C_CYAN}⟳ [9/10] Mengonfigurasi Nginx Web Server...${C_RESET}"
PHP_FPM_SOCK=""
for sock in "/run/php/php${PHP_TARGET}-fpm.sock" "/run/php/php8.4-fpm.sock" "/run/php/php8.5-fpm.sock" "/run/php/php8.3-fpm.sock"; do
    if [ -S "$sock" ]; then
        PHP_FPM_SOCK="$sock"
        break
    fi
done
if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="unix:/run/php/php${PHP_TARGET}-fpm.sock"
else
    PHP_FPM_SOCK="unix:${PHP_FPM_SOCK}"
fi

NGINX_SITE_CONF="/etc/nginx/sites-available/uteparts.conf"
cat > "$NGINX_SITE_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${TARGET_DIR}/public;

    index index.php index.html;
    charset utf-8;

    client_max_body_size 64M;
    server_tokens off;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass ${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf "$NGINX_SITE_CONF" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || systemctl restart nginx 2>/dev/null || true

# STEP 10: Supervisor Worker, Cron & Firewall Hardening
echo -e "${C_CYAN}⟳ [10/10] Mengonfigurasi Supervisor Worker, Cron Scheduler, & Firewall...${C_RESET}"
SUPERVISOR_CONF="/etc/supervisor/conf.d/uteparts-worker.conf"
cat > "$SUPERVISOR_CONF" <<EOF
[program:uteparts-worker]
process_name=%(program_name)s_%(process_num)02d
command=${PHP_BIN} ${TARGET_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --memory=128
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

supervisorctl reread >/dev/null 2>&1 || true
supervisorctl update >/dev/null 2>&1 || true
supervisorctl restart uteparts-worker:* >/dev/null 2>&1 || true

# Cron Job Laravel
cat > /etc/cron.d/uteparts <<EOF
* * * * * www-data ${PHP_BIN} ${TARGET_DIR}/artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/uteparts

# Firewall & Fail2ban (Kunci port DB 3306, buka hanya web & SSH)
if command -v ufw >/dev/null 2>&1; then
    ufw allow 22/tcp >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    ufw delete allow 3306/tcp >/dev/null 2>&1 || true
    ufw --force enable >/dev/null 2>&1 || true
fi
systemctl enable --now fail2ban >/dev/null 2>&1 || true

# SSL Certbot jika diminta
if [ "$ENABLE_SSL" = "y" ] && [ "$IS_FQDN" -eq 1 ]; then
    echo -e "${C_CYAN}⟳ Mengajukan sertifikat SSL HTTPS Let's Encrypt...${C_RESET}"
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect >/dev/null 2>&1 || {
        echo -e "${C_YELLOW}⚠️  Verifikasi DNS Certbot belum selesai atau gagal. Anda dapat menjalankannya nanti dengan: certbot --nginx -d ${DOMAIN}${C_RESET}"
    }
fi

# Optional: AI Development Stack Setup (Hanya jika disetujui pengguna)
if [ -f "${TARGET_DIR}/deploy/setup-ai-dev-stack.sh" ]; then
    echo ""
    read -rp "Ingin memasang AI Dev Tools (OpenCode CLI, 9router, MCP) di VPS ini? (y/N) [n]: " INSTALL_AI_TOOLS
    INSTALL_AI_TOOLS="${INSTALL_AI_TOOLS:-n}"
    if [[ "$INSTALL_AI_TOOLS" =~ ^[Yy]$ ]]; then
        echo -e "${C_CYAN}⟳ Memasang AI Development Stack...${C_RESET}"
        bash "${TARGET_DIR}/deploy/setup-ai-dev-stack.sh" || true
    fi
fi

# ==============================================================================
# HASIL INSTALASI & INFORMASI LENGKAP
# ==============================================================================
FINAL_URL="${APP_URL}"
if [ "$ENABLE_SSL" = "y" ] && [ "$IS_FQDN" -eq 1 ]; then
    FINAL_URL="https://${DOMAIN}"
fi

echo ""
echo -e "${C_BOLD}${C_GREEN}╔══════════════════════════════════════════════════════════════════════════════╗${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}║             🎉 INSTALASI VPS BERHASIL & SISTEM TELAH AKTIF!                  ║${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}╚══════════════════════════════════════════════════════════════════════════════╝${C_RESET}"
echo ""
echo -e "  ${C_BOLD}${C_WHITE}Alamat Akses Sistem:${C_RESET}"
echo -e "  • ${C_WHITE}Backoffice & POS${C_RESET} : ${C_CYAN}${FINAL_URL}/app/login${C_RESET}"
if [ "$APP_ROLE" = "full" ]; then
echo -e "  • ${C_WHITE}Marketplace Front${C_RESET}: ${C_CYAN}${FINAL_URL}/${C_RESET}"
fi
echo ""
echo -e "  ${C_BOLD}${C_WHITE}Kredensial Super Administrator:${C_RESET}"
echo -e "  • ${C_WHITE}Email${C_RESET}            : ${C_YELLOW}${ADMIN_EMAIL}${C_RESET}"
echo -e "  • ${C_WHITE}Password${C_RESET}         : ${C_YELLOW}${ADMIN_PASS}${C_RESET}"
echo ""
echo -e "  ${C_BOLD}${C_WHITE}Detail Database (Lokal Terisolasi):${C_RESET}"
echo -e "  • ${C_WHITE}Database Host${C_RESET}    : ${C_GREEN}127.0.0.1${C_RESET} (Port: 3306 - Firewall Locked)"
echo -e "  • ${C_WHITE}Database Name${C_RESET}    : ${C_WHITE}${DB_NAME}${C_RESET}"
echo -e "  • ${C_WHITE}Database User${C_RESET}    : ${C_WHITE}${DB_USER}${C_RESET}"
echo -e "  • ${C_WHITE}Database Pass${C_RESET}    : ${C_WHITE}${DB_PASS}${C_RESET}"
echo ""
echo -e "  ${C_BOLD}${C_WHITE}Perintah Pemeliharaan Server:${C_RESET}"
echo -e "  • Backup Database : ${C_DIM}sudo -u www-data ${PHP_BIN} ${TARGET_DIR}/artisan ute:backup${C_RESET}"
echo -e "  • Status Queue    : ${C_DIM}supervisorctl status uteparts-worker:*${C_RESET}"
echo -e "  • Reload Nginx    : ${C_DIM}systemctl reload nginx${C_RESET}"
echo ""
echo -e "${C_CYAN}════════════════════════════════════════════════════════════════════════════════${C_RESET}"
