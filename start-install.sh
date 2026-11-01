#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP & Marketplace — One-Click Universal Launcher (Linux & macOS)
# Mendukung auto-fix seluruh dependensi perangkat kosongan, database & multi-device
# Port Default: 1741 (Akses Lokal & Jaringan LAN / Wi-Fi)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   Ute Parts ERP & Marketplace — One-Click Universal Launcher"
echo "   Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)"
echo "======================================================================"
echo ""

# 1. Deteksi Sistem Operasi
OS_TYPE="$(uname -s)"
echo "ℹ [INFO] Sistem Operasi terdeteksi: $OS_TYPE ($(uname -m))"

# 2. Deteksi PHP Binary
PHP_BIN=""
CANDIDATES=(
    "$(which php 2>/dev/null || true)"
    "/usr/bin/php8.4"
    "/usr/bin/php8.3"
    "/usr/local/bin/php"
    "/opt/homebrew/bin/php"
    "/usr/local/lsws/lsphp85/bin/php"
    "/usr/local/lsws/lsphp84/bin/php"
)

for bin in "${CANDIDATES[@]}"; do
    if [ -n "$bin" ] && [ -x "$bin" ]; then
        # Cek versi PHP minimal 8.3
        VER_OK="$("$bin" -r 'echo version_compare(PHP_VERSION, "8.3.0", ">=") ? "1" : "0";' 2>/dev/null || echo "0")"
        if [ "$VER_OK" = "1" ]; then
            PHP_BIN="$bin"
            break
        fi
    fi
done

# 3. Auto-Install PHP jika belum ada di perangkat kosongan
if [ -z "$PHP_BIN" ]; then
    echo "ℹ PHP >= 8.3 belum terpasang. Menjalankan auto-installer untuk perangkat kosongan..."
    
    if [ "$OS_TYPE" = "Linux" ]; then
        if command -v apt-get >/dev/null 2>&1; then
            echo "ℹ [Debian/Ubuntu] Memasang dependensi & repositori PHP..."
            sudo apt-get update -y
            sudo apt-get install -y software-properties-common lsb-release ca-certificates apt-transport-https curl unzip git || true
            
            # Tambahkan ppa:ondrej/php jika di Ubuntu
            if command -v add-apt-repository >/dev/null 2>&1; then
                sudo add-apt-repository -y ppa:ondrej/php || true
                sudo apt-get update -y || true
            fi
            
            sudo apt-get install -y \
                php8.4-cli php8.4-fpm php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-gd \
                php8.4-mbstring php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-fileinfo unzip curl || \
            sudo apt-get install -y \
                php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 php8.3-xml php8.3-gd \
                php8.3-mbstring php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-fileinfo unzip curl || \
            sudo apt-get install -y \
                php-cli php-mysql php-sqlite3 php-xml php-gd php-mbstring php-curl php-zip php-intl php-bcmath unzip curl || true
            
            PHP_BIN="$(which php || echo /usr/bin/php8.4)"
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
    echo "❌ [ERROR] PHP >= 8.3 tidak ditemukan di sistem!"
    echo "Panduan:"
    echo "  - Ubuntu/Debian : sudo add-apt-repository -y ppa:ondrej/php && sudo apt install -y php8.4-cli php8.4-xml php8.4-sqlite3 php8.4-mysql php8.4-curl php8.4-mbstring php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd unzip"
    echo "  - macOS         : brew install php"
    exit 1
fi

echo "✔ [OK] PHP terverifikasi: $PHP_BIN ($("$PHP_BIN" -v | head -n 1))"

# 4. Validasi Ekstensi PHP Kritis (XML, SQLite, MySQL, Mbstring)
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
    if [ -n "$MISSING_EXTS" ]; then
        echo "ℹ [AUTO-FIX] Memasang ekstensi PHP yang belum aktif:$MISSING_EXTS..."
        PHP_VER="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
        sudo apt-get update -y && sudo apt-get install -y "php${PHP_VER}-xml" "php${PHP_VER}-sqlite3" "php${PHP_VER}-mysql" "php${PHP_VER}-mbstring" "php${PHP_VER}-curl" unzip || true
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
        $COMPOSER_CMD install --no-interaction --prefer-dist --optimize-autoloader || \
        $COMPOSER_CMD install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-dom --ignore-platform-req=ext-xml --ignore-platform-req=ext-simplexml --ignore-platform-req=ext-xmlwriter || \
        $COMPOSER_CMD install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs || true
    else
        echo "⚠️ [WARN] Composer belum tersedia. Dependensi dapat dilengkapi via wizard GUI."
    fi
fi

# 7b. Otomatisasi Hak Akses MySQL & Bind-Address LAN (Untuk Komputer Server Induk)
if command -v mysql >/dev/null 2>&1; then
    for mycnf in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mariadb.conf.d/50-server.cnf /etc/mysql/my.cnf; do
        if [ -f "$mycnf" ] && grep -q "127.0.0.1" "$mycnf" 2>/dev/null; then
            echo "ℹ [AUTO-FIX] Menyesuaikan MySQL bind-address ke 0.0.0.0 agar dapat diakses komputer kasir LAN..."
            sudo sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/g' "$mycnf" 2>/dev/null || true
            sudo systemctl restart mysql 2>/dev/null || sudo service mysql restart 2>/dev/null || true
        fi
    done
    if sudo mysql -e "SELECT 1;" >/dev/null 2>&1; then
        sudo mysql -e "CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null || true
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
        sudo ufw allow 3306/tcp comment 'Ute Parts MySQL LAN' >/dev/null 2>&1 || true
    fi
fi

LOCAL_URL="http://localhost:${PORT}/install.php"
NETWORK_URL=""
if [ -n "$LAN_IP" ]; then
    NETWORK_URL="http://${LAN_IP}:${PORT}/install.php"
fi

# 10. Buka Web Browser Otomatis
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$LOCAL_URL" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    open "$LOCAL_URL" >/dev/null 2>&1 &
fi

# 11. Jalankan Built-in Web Server (0.0.0.0 untuk akses Localhost & Multi-Device LAN)
echo "======================================================================"
echo "   Server Siap & Berjalan:"
echo "   - Dari Komputer Ini       : $LOCAL_URL"
if [ -n "$NETWORK_URL" ]; then
echo "   - Dari HP/iPhone/iPad/LAN : $NETWORK_URL"
fi
echo "----------------------------------------------------------------------"
echo "   Petunjuk Multi-Client Satu Database:"
echo "   - Komputer Pusat / Server : Pilih 'Mode Server' di Web Installer."
echo "   - Komputer Kasir / Client : Pilih 'Mode Client' -> 'Auto-Scan Server'."
echo "======================================================================"
echo "   Tekan Ctrl+C untuk menghentikan server."
echo "======================================================================"
echo ""
exec "$PHP_BIN" -S 0.0.0.0:"$PORT" -t public
