#!/usr/bin/env bash
# ==============================================================================
# ERP & POS System — One-Click Universal Launcher (Linux & macOS)
# Mendukung auto-fix seluruh dependensi perangkat kosongan, database & multi-device
# Port Default: 1741 (Akses Lokal & Jaringan LAN / Wi-Fi)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   ERP & POS System — One-Click Universal Launcher"
echo "   Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)"
echo "======================================================================"
echo ""

# 1. Deteksi Sistem Operasi
OS_TYPE="$(uname -s)"
echo "ℹ [INFO] Sistem Operasi terdeteksi: $OS_TYPE ($(uname -m))"

# 1b. Alokasi Swap Memory Otomatis (2GB jika RAM <= 2GB)
if [ "$OS_TYPE" = "Linux" ] && [ -f /proc/meminfo ]; then
    MEM_TOTAL_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    SWAP_TOTAL_MB=$(awk '/SwapTotal/ {print int($2/1024)}' /proc/meminfo)
    if [ "$MEM_TOTAL_MB" -gt 0 ] && [ "$MEM_TOTAL_MB" -le 2048 ] && [ "$SWAP_TOTAL_MB" -lt 1024 ]; then
        echo "ℹ [AUTO-FIX] RAM server <= 2GB (${MEM_TOTAL_MB} MB). Menyiapkan alokasi swap memory 2GB..."
        if [ ! -f /swapfile ]; then
            sudo fallocate -l 2G /swapfile 2>/dev/null || sudo dd if=/dev/zero of=/swapfile bs=1M count=2048 2>/dev/null || true
            sudo chmod 600 /swapfile 2>/dev/null || true
            sudo mkswap /swapfile 2>/dev/null || true
        fi
        sudo swapon /swapfile 2>/dev/null || true
        if ! grep -q "/swapfile" /etc/fstab 2>/dev/null; then
            echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null 2>&1 || true
        fi
        echo "✔ [OK] Swap memory 2GB aktif & persisten di /etc/fstab."
    fi
fi

# 2. Deteksi PHP Binary
PHP_BIN=""
CANDIDATES=(
    "/usr/bin/php8.5"
    "/usr/local/lsws/lsphp85/bin/php"
    "/usr/bin/php8.4"
    "/usr/local/lsws/lsphp84/bin/php"
    "$(which php 2>/dev/null || true)"
    "/usr/local/bin/php"
    "/opt/homebrew/bin/php"
)

for bin in "${CANDIDATES[@]}"; do
    if [ -n "$bin" ] && [ -x "$bin" ]; then
        # Cek versi PHP minimal 8.4 (wajib untuk Laravel 13 & Symfony 8)
        VER_OK="$("$bin" -r 'echo version_compare(PHP_VERSION, "8.4.0", ">=") ? "1" : "0";' 2>/dev/null || echo "0")"
        if [ "$VER_OK" = "1" ]; then
            PHP_BIN="$bin"
            break
        fi
    fi
done

# 3. Auto-Install PHP jika belum ada di perangkat kosongan
if [ -z "$PHP_BIN" ]; then
    echo "ℹ PHP >= 8.4 belum terpasang. Menjalankan auto-installer untuk perangkat kosongan..."
    
    if [ "$OS_TYPE" = "Linux" ]; then
        if command -v apt-get >/dev/null 2>&1; then
            echo "ℹ [Debian/Ubuntu] Memasang dependensi & repositori PHP 8.4..."
            sudo apt-get update -y
            sudo apt-get install -y software-properties-common lsb-release ca-certificates apt-transport-https curl unzip git || true
            
            # Tambahkan ppa:ondrej/php jika di Ubuntu
            if command -v add-apt-repository >/dev/null 2>&1; then
                sudo add-apt-repository -y ppa:ondrej/php || true
                sudo apt-get update -y || true
            fi
            
            sudo apt-get install -y \
                php8.4-cli php8.4-fpm php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-gd \
                php8.4-mbstring php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-fileinfo \
                php8.4-opcache php8.4-imagick unzip curl || true
            
            PHP_BIN="$(which php8.4 || which php || echo /usr/bin/php8.4)"
        elif command -v dnf >/dev/null 2>&1; then
            echo "ℹ [Fedora/RHEL] Memasang dependensi PHP via DNF..."
            sudo dnf install -y php-cli php-mysqlnd php-pdo php-sqlite3 php-xml php-gd php-mbstring php-curl php-zip php-intl php-bcmath unzip curl || true
            PHP_BIN="$(which php || true)"
        elif command -v pacman >/dev/null 2>&1; then
            echo "ℹ [Arch Linux] Memasang dependensi PHP via Pacman..."
            sudo pacman -Sy --noconfirm php php-sqlite php-gd php-intl unzip curl || true
            PHP_BIN="$(which php || true)"
        fi
    elif [ "$OS_TYPE" = "Darwin" ]; then
        # macOS
        if command -v brew >/dev/null 2>&1; then
            echo "ℹ [macOS] Memasang PHP via Homebrew..."
            brew install php || true
            PHP_BIN="$(which php || echo /opt/homebrew/bin/php)"
        else
            echo "⚠️ [macOS] Homebrew belum terpasang."
            echo "Silakan pasang Homebrew & PHP dengan perintah:"
            echo '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)" && brew install php'
        fi
    fi
fi

# Validasi ulang PHP
if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
    echo "❌ [ERROR] PHP >= 8.4 tidak ditemukan di sistem!"
    echo "Panduan:"
    echo "  - Ubuntu/Debian : sudo add-apt-repository -y ppa:ondrej/php && sudo apt update && sudo apt install -y php8.4-cli php8.4-xml php8.4-sqlite3 php8.4-mysql php8.4-curl php8.4-mbstring php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd unzip"
    echo "  - macOS         : brew install php"
    exit 1
fi

VER_OK="$("$PHP_BIN" -r 'echo version_compare(PHP_VERSION, "8.4.0", ">=") ? "1" : "0";' 2>/dev/null || echo "0")"
if [ "$VER_OK" != "1" ]; then
    CURR_VER="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo "unknown")"
    echo "❌ [ERROR] Versi PHP saat ini ($CURR_VER) kurang dari 8.4!"
    echo "Laravel 13 & Symfony 8 memerlukan minimal PHP 8.4.0."
    echo "Silakan gunakan /usr/bin/php8.4 atau update PHP ke 8.4."
    exit 1
fi

echo "✔ [OK] PHP terverifikasi: $PHP_BIN ($("$PHP_BIN" -v | head -n 1))"

# 4. Validasi Ekstensi PHP Kritis (XML, SQLite, MySQL, Mbstring, Opcache, Imagick)
if command -v apt-get >/dev/null 2>&1; then
    MISSING_EXTS=""
    if ! "$PHP_BIN" -m | grep -qi "dom" || ! "$PHP_BIN" -m | grep -qi "xml"; then
        MISSING_EXTS="$MISSING_EXTS php-xml"
    fi
    if ! "$PHP_BIN" -m | grep -qi "sqlite"; then
        MISSING_EXTS="$MISSING_EXTS php-sqlite3"
    fi
    if ! "$PHP_BIN" -m | grep -qi "pdo_mysql"; then
        MISSING_EXTS="$MISSING_EXTS php-mysql"
    fi
    if ! "$PHP_BIN" -m | grep -qi "opcache"; then
        MISSING_EXTS="$MISSING_EXTS php-opcache"
    fi
    if [ -n "$MISSING_EXTS" ]; then
        echo "ℹ [AUTO-FIX] Memasang ekstensi PHP yang belum aktif:$MISSING_EXTS..."
        PHP_VER="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
        sudo apt-get update -y && sudo apt-get install -y "php${PHP_VER}-xml" "php${PHP_VER}-sqlite3" "php${PHP_VER}-mysql" "php${PHP_VER}-mbstring" "php${PHP_VER}-curl" "php${PHP_VER}-imagick" "php${PHP_VER}-opcache" unzip || true
    fi
fi

# 5. Siapkan Direktori Storage, Database & Cache
echo "ℹ [AUTO-FIX] Menyiapkan direktori storage, database & izin tulis..."
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public database
chmod -R 775 storage bootstrap/cache database 2>/dev/null || chmod -R 777 storage bootstrap/cache database 2>/dev/null || true

# 6. Deteksi & Siapkan Composer
COMPOSER_CMD=""
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="composer"
elif [ -f "./composer.phar" ]; then
    COMPOSER_CMD="$PHP_BIN ./composer.phar"
else
    echo "ℹ [AUTO-FIX] Mengunduh composer.phar resmi..."
    if command -v curl >/dev/null 2>&1; then
        curl -sS -L https://getcomposer.org/composer-stable.phar -o ./composer.phar || true
    fi
    if [ ! -f "./composer.phar" ] && command -v curl >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir=. --filename=composer.phar || true
    fi
    if [ -f "./composer.phar" ]; then
        chmod +x ./composer.phar
        COMPOSER_CMD="$PHP_BIN ./composer.phar"
    fi
fi

# 7. Instalasi Dependensi Vendor jika belum ada
if [ ! -f "vendor/autoload.php" ]; then
    if [ -n "$COMPOSER_CMD" ]; then
        echo "ℹ [AUTO-FIX] Menjalankan composer install untuk melengkapi vendor..."
        $COMPOSER_CMD install --no-interaction --prefer-dist --no-scripts || \
        $COMPOSER_CMD install --no-interaction --prefer-dist --no-scripts --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter || \
        $COMPOSER_CMD install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs || true

        if [ -f "vendor/autoload.php" ]; then
            echo "ℹ [AUTO-FIX] Mendaftarkan package Laravel..."
            "$PHP_BIN" artisan package:discover --ansi || true
        fi
    else
        echo "⚠️ [WARN] Composer belum tersedia. Dependensi dapat dilengkapi via wizard GUI."
    fi
fi

# 7b. Otomatisasi Hak Akses MySQL & Bind-Address LAN (Untuk Komputer Server Induk)
if command -v mysql >/dev/null 2>&1; then
    # Nyalakan service MySQL jika belum aktif
    if ! netstat -tuln 2>/dev/null | grep -q ":3306 " && ! ss -tuln 2>/dev/null | grep -q ":3306 "; then
        sudo systemctl start mysql 2>/dev/null || sudo systemctl start mariadb 2>/dev/null || sudo service mysql start 2>/dev/null || true
    fi
fi

# 8. Konfigurasi Port 1741 (Unik & Jarang Dipakai)
PORT=1741
if lsof -i :"$PORT" >/dev/null 2>&1 || nc -z 127.0.0.1 "$PORT" >/dev/null 2>&1; then
    echo "⚠️ Port 1741 sedang dipakai oleh proses lain. Mencari port alternatif..."
    while lsof -i :"$PORT" >/dev/null 2>&1 || nc -z 127.0.0.1 "$PORT" >/dev/null 2>&1; do
        PORT=$((PORT + 1))
        if [ "$PORT" -gt 1760 ]; then
            echo "❌ [ERROR] Rentang port 1741-1760 sedang terpakai."
            exit 1
        fi
    done
fi
echo "✔ [OK] Port aktif: $PORT"

# 9. Deteksi IP Jaringan Lokal (Wi-Fi / LAN)
LAN_IP=""
if command -v hostname >/dev/null 2>&1; then
    LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
fi
if [ -z "$LAN_IP" ] && command -v ip >/dev/null 2>&1; then
    LAN_IP=$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7}')
fi
if [ -z "$LAN_IP" ] && command -v ifconfig >/dev/null 2>&1; then
    LAN_IP=$(ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n 1)
fi

# Buka firewall port 1741 & 3306 jika UFW aktif di Linux
if command -v ufw >/dev/null 2>&1; then
    if sudo ufw status 2>/dev/null | grep -qi "active"; then
        sudo ufw allow "$PORT"/tcp >/dev/null 2>&1 || true
    fi
fi

MYSQL_ACTIVE=0
if netstat -tuln 2>/dev/null | grep -q ":3306 " || ss -tuln 2>/dev/null | grep -q ":3306 " || lsof -i :3306 >/dev/null 2>&1; then
    MYSQL_ACTIVE=1
fi

TARGET_PATH="/install.php"
if [ -f "storage/installed.lock" ]; then
    if [ "$MYSQL_ACTIVE" = "1" ] || grep -q "DB_CONNECTION=sqlite" ".env" 2>/dev/null; then
        TARGET_PATH="/app/login"
    fi
fi

if [ "$MYSQL_ACTIVE" = "0" ]; then
    echo "⚠️  [PERHATIAN] Service MySQL port 3306 belum aktif."
    echo "ℹ  [TIPS] Anda dapat memilih database 'SQLite' di installer jika ingin tanpa MySQL."
fi

LOCAL_URL="http://localhost:${PORT}${TARGET_PATH}"
NETWORK_URL=""
if [ -n "$LAN_IP" ]; then
    NETWORK_URL="http://${LAN_IP}:${PORT}${TARGET_PATH}"
fi

# 10. Buka Web Browser Otomatis
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$LOCAL_URL" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    open "$LOCAL_URL" >/dev/null 2>&1 &
fi

# 11. Jalankan Built-in Web Server di Background (Tetap aktif walau terminal ditutup)
echo "ℹ [INFO] Menyalakan server di background pada port $PORT..."
mkdir -p storage/framework storage/logs
nohup "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public > storage/logs/server.log 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > storage/framework/server.pid
sleep 1

echo "======================================================================"
echo "   Server ERP & POS Berhasil Berjalan di Background!"
echo "   - Dari Komputer Ini       : $LOCAL_URL"
if [ -n "$NETWORK_URL" ]; then
echo "   - Dari HP/iPhone/iPad/LAN : $NETWORK_URL"
fi
echo "   - PID Proses Server       : $SERVER_PID"
echo "----------------------------------------------------------------------"
echo "   [INFO] Server tetap aktif melayani meskipun terminal ini ditutup."
echo "   [INFO] Untuk menghentikan server, jalankan: ./stop-server.sh"
echo "======================================================================"
echo ""
