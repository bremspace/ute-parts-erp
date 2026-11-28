#!/usr/bin/env bash
# ==============================================================================
# UTE PARTS ERP - Docker Installer khusus NODE CLIENT (Linux / VPS)
# ==============================================================================
# Stack : Docker Compose (Apache + PHP 8.4) + MySQL 8.0
# Peran : primary_store (default) atau cloud_storefront
# Catatan: TIDAK menjalankan seeder apa pun. Data awal ditarik dari cloud via
#          `php artisan sync:bootstrap`.
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.client"
cd "$SCRIPT_DIR"

echo "============================================================"
echo "   UTE PARTS ERP - Docker Installer (CLIENT NODE)"
echo "============================================================"
echo ""

# ----------------------------- helpers ---------------------------------------
ask() {
    local answer=""
    read -r -p "$1" answer || true
    if [ -z "$answer" ]; then printf '%s' "${2:-}"; else printf '%s' "$answer"; fi
}

ask_secret() {
    local answer=""
    read -rs -p "$1" answer || true
    # Newline visual diprint ke stderr agar TIDAK ikut tersimpan di variabel
    # melalui command substitution (read -s tidak men-echo Enter).
    printf '\n' >&2
    printf '%s' "$answer"
}

# Escape nilai untuk string berkutip di file .env (aturan phpdotenv:
# \\ -> \\\\, " -> \", $ -> \$. Backtick tidak perlu di-escape).
# Dipakai bersama printf '%s' sehingga tidak ada interpretasi shell kedua.
esc() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e 's/\$/\\$/g'
}

mask() {
    local v="$1"
    if [ -z "$v" ]; then
        printf '(kosong)'
    elif [ "${#v}" -le 4 ]; then
        printf '****'
    else
        printf '%s****%s' "${v:0:4}" "${v: -2}"
    fi
}

gen_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    elif command -v php >/dev/null 2>&1; then
        php -r 'echo bin2hex(random_bytes(32));'
    else
        cat /dev/urandom | tr -dc 'a-zA-Z0-9' | head -c 40
    fi
}

get_env() {
    sed -n "s/^${1}=//p" "$ENV_FILE" 2>/dev/null | head -1 | sed -e 's/^"//' -e 's/"$//'
}

# --------------------------- wizard ------------------------------------------
run_wizard() {
    echo "------------------------------------------------------------"
    echo " WIZARD KONFIGURASI NODE"
    echo "------------------------------------------------------------"
    echo ""

    echo "Pilih peran node:"
    echo "  [1] client  (primary_store)     - DEFAULT. Toko lokal, DB sendiri,"
    echo "                                    data awal ditarik dari cloud."
    echo "  [2] cloud   (cloud_storefront)  - Sumber data kanonik di uteparts.id."
    local role_input
    role_input="$(ask "Peran (1/2, default 1): " "1")"
    case "$role_input" in
        1|client|Client|CLIENT) ROLE="client" ;;
        2|cloud|Cloud|CLOUD) ROLE="cloud" ;;
        *) echo "[FAIL] Peran tidak valid."; exit 1 ;;
    esac

    echo ""
    if [ "$ROLE" = "client" ]; then
        REMOTE_URL="$(ask "URL cloud (default: https://uteparts.id): " "https://uteparts.id")"
        [ -n "$REMOTE_URL" ] || REMOTE_URL="https://uteparts.id"
        NODE_ID="$(ask "Node ID client (default: STORE-01): " "STORE-01")"
    else
        echo "[INFO] Node cloud bersifat pasif receiver; SYNC_REMOTE_URL boleh kosong."
        REMOTE_URL="$(ask "URL cloud (kosongkan / '-' untuk pasif, contoh: https://uteparts.id): " "")"
        [ "$REMOTE_URL" = "-" ] && REMOTE_URL=""
        NODE_ID="$(ask "Node ID cloud (default: CLOUD-01): " "CLOUD-01")"
    fi
    [ -n "$NODE_ID" ] || NODE_ID="STORE-01"

    echo ""
    echo "API secret sync HARUS sama persis dengan node cloud (minimal 32 karakter)."
    API_SECRET="$(ask_secret "API secret sync (Enter = generate otomatis): ")"
    if [ -z "$API_SECRET" ]; then
        API_SECRET="$(gen_secret)"
        echo "[INFO] API secret dibuat otomatis. SALIN ke node lawan bicara!"
    elif [ "${#API_SECRET}" -lt 32 ]; then
        echo "[WARN] API secret < 32 karakter. Disarankan lebih panjang."
    fi

    echo ""
    ADMIN_EMAIL="$(ask "Email admin lokal (default: admin@uteparts.id): " "admin@uteparts.id")"
    [ -n "$ADMIN_EMAIL" ] || ADMIN_EMAIL="admin@uteparts.id"
    ADMIN_PASSWORD="$(ask_secret "Password admin lokal (Enter = generate otomatis): ")"
    if [ -z "$ADMIN_PASSWORD" ]; then
        ADMIN_PASSWORD="$(gen_secret | cut -c1-20)"
        echo "[INFO] Password admin dibuat otomatis (lihat ringkasan, tersimpan di .env.client)."
    elif [ "${#ADMIN_PASSWORD}" -lt 8 ]; then
        echo "[FAIL] Password admin minimal 8 karakter."
        exit 1
    fi

    echo ""
    BRANCH_CODE="$(ask "Kode cabang opsional, '-' = tanpa cabang (default: CBG-01): " "CBG-01")"
    [ "$BRANCH_CODE" = "-" ] && BRANCH_CODE=""
    if [ -n "$BRANCH_CODE" ]; then
        BRANCH_NAME="$(ask "Nama cabang (default: Cabang Utama): " "Cabang Utama")"
        [ -n "$BRANCH_NAME" ] || BRANCH_NAME="Cabang Utama"
    else
        BRANCH_NAME=""
    fi
}

load_existing() {
    ROLE="$(get_env SYNC_NODE_ROLE)"
    if [ "$ROLE" = "cloud_storefront" ]; then ROLE="cloud"; else ROLE="client"; fi
    REMOTE_URL="$(get_env SYNC_REMOTE_URL)"
    NODE_ID="$(get_env SYNC_NODE_ID)"
    API_SECRET="$(get_env SYNC_API_SECRET)"
    ADMIN_EMAIL="$(get_env ADMIN_EMAIL)"
    ADMIN_PASSWORD="$(get_env ADMIN_PASSWORD)"
    BRANCH_CODE="$(get_env ADMIN_BRANCH_CODE)"
    BRANCH_NAME="$(get_env ADMIN_BRANCH_NAME)"
    [ -n "$ADMIN_EMAIL" ] || ADMIN_EMAIL="admin@uteparts.id"
    [ -n "$NODE_ID" ] || NODE_ID="STORE-01"
}

write_env() {
    local app_name="UTE PARTS ERP - CLIENT"
    local client_mode="true"
    local node_role="primary_store"
    if [ "$ROLE" = "cloud" ]; then
        app_name="UTE PARTS ERP - CLOUD"
        client_mode="false"
        node_role="cloud_storefront"
    fi

    # Tulis lewat printf, BUKAN heredoc: heredoc akan memakan backslash escape
    # sehingga nilai dengan $ / " / backslash bisa merusak parser dotenv.
    w() { printf '%s\n' "$1" >> "$ENV_FILE"; }
    : > "$ENV_FILE"

    w '# Dibuat oleh installers/docker-client/install.sh - JANGAN commit file ini.'
    w "APP_NAME=\"$(esc "$app_name")\""
    w 'APP_ENV=production'
    w 'APP_DEBUG=false'
    w 'APP_URL=http://localhost'
    w ''
    w 'APP_LOCALE=id'
    w 'APP_FALLBACK_LOCALE=en'
    w 'APP_FAKER_LOCALE=en_US'
    w 'APP_MAINTENANCE_DRIVER=file'
    w 'BCRYPT_ROUNDS=12'
    w ''
    w 'LOG_CHANNEL=stack'
    w 'LOG_STACK=single'
    w 'LOG_LEVEL=debug'
    w ''
    w 'DB_CONNECTION=mysql'
    w 'DB_HOST=mysql'
    w 'DB_PORT=3306'
    w 'DB_DATABASE=ute_parts_client'
    w 'DB_USERNAME=ute_client'
    w 'DB_PASSWORD=ute_client_secret'
    w ''
    w 'SESSION_DRIVER=database'
    w 'SESSION_LIFETIME=120'
    w 'SESSION_ENCRYPT=false'
    w 'SESSION_PATH=/'
    w 'SESSION_DOMAIN=null'
    w 'SESSION_SECURE_COOKIE=false'
    w ''
    w 'BROADCAST_CONNECTION=log'
    w 'FILESYSTEM_DISK=local'
    w 'QUEUE_CONNECTION=database'
    w 'CACHE_STORE=database'
    w ''
    w 'MAIL_MAILER=log'
    w 'MAIL_FROM_ADDRESS="hello@uteparts.id"'
    w 'MAIL_FROM_NAME="UTE PARTS ERP"'
    w 'BREMSPACE_LICENSE_ENABLED=false'
    w "VITE_APP_NAME=\"$(esc "$app_name")\""
    w ''
    w '# Hybrid Sync'
    w 'SYNC_ENABLED=true'
    w "SYNC_NODE_ROLE=${node_role}"
    w "SYNC_NODE_ID=\"$(esc "$NODE_ID")\""
    w "SYNC_REMOTE_URL=\"$(esc "$REMOTE_URL")\""
    w "SYNC_API_SECRET=\"$(esc "$API_SECRET")\""
    w 'SYNC_PULL_INTERVAL_SECONDS=30'
    w "SYNC_CLIENT_MODE=${client_mode}"
    w ''
    w '# Admin LOKAL untuk login awal (bukan seeder). Satu user, dibuat bila belum ada.'
    w "ADMIN_EMAIL=\"$(esc "$ADMIN_EMAIL")\""
    w "ADMIN_PASSWORD=\"$(esc "$ADMIN_PASSWORD")\""
    w "ADMIN_NAME=\"Admin ${ROLE}\""
    w "ADMIN_BRANCH_CODE=\"$(esc "$BRANCH_CODE")\""
    w "ADMIN_BRANCH_NAME=\"$(esc "$BRANCH_NAME")\""
    w 'ADMIN_FORCE=false'

    chmod 600 "$ENV_FILE"
    echo "[OK] File .env.client tersimpan (mode 600)."
}

show_summary() {
    echo ""
    echo "------------------------------------------------------------"
    echo " RINGKASAN KONFIGURASI (sebelum build)"
    echo "------------------------------------------------------------"
    echo " Peran node        : $ROLE ($( [ "$ROLE" = "cloud" ] && echo cloud_storefront || echo primary_store ))"
    echo " SYNC_CLIENT_MODE  : $( [ "$ROLE" = "cloud" ] && echo false || echo true )"
    echo " URL cloud         : ${REMOTE_URL:-(kosong / pasif)}"
    echo " Node ID           : $NODE_ID"
    echo " API secret        : $(mask "$API_SECRET")"
    echo " Admin lokal       : $ADMIN_EMAIL"
    echo " Password admin    : $(mask "$ADMIN_PASSWORD")"
    echo " Cabang opsional   : ${BRANCH_CODE:-(tidak ada)}${BRANCH_NAME:+ - $BRANCH_NAME}"
    echo " Database          : mysql:3306 / ute_parts_client (volume permanen)"
    echo " Port host         : 80:80"
    echo " Image             : ute-parts-client:latest"
    echo " Seeder            : TIDAK DIJALANKAN (data via sync:bootstrap)"
    echo "------------------------------------------------------------"
}

# --------------------------- prerequisites -----------------------------------
if ! command -v docker >/dev/null 2>&1; then
    echo "[FAIL] Docker belum terinstall."
    echo "   curl -fsSL https://get.docker.com | sh"
    echo "   sudo usermod -aG docker \$USER"
    exit 1
fi

COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    echo "[FAIL] Docker Compose belum terinstall (apt-get install docker-compose-plugin)."
    exit 1
fi

echo "[OK] Docker    : $(docker --version)"
echo "[OK] Compose   : $($COMPOSE_CMD version)"
echo ""

# --------------------------- config ------------------------------------------
if [ -f "$ENV_FILE" ]; then
    echo "[INFO] .env.client sudah ada."
    REUSE="$(ask "Gunakan konfigurasi yang ada? (Y/n, default Y): " "Y")"
    if [[ "$REUSE" =~ ^[Yy]$ ]] || [ -z "$REUSE" ]; then
        load_existing
    else
        cp "$ENV_FILE" "$ENV_FILE.bak.$(date +%Y%m%d%H%M%S)"
        echo "[INFO] Backup .env.client dibuat. Wizard ulang..."
        run_wizard
        write_env
    fi
else
    run_wizard
    write_env
fi

show_summary
PROCEED="$(ask "Lanjutkan build & jalankan container? (Y/n, default Y): " "Y")"
if [[ ! "$PROCEED" =~ ^[Yy]$ ]] && [ -n "$PROCEED" ]; then
    echo "Dibatalkan. File .env.client tetap tersimpan."
    exit 0
fi

# --------------------------- action ------------------------------------------
echo ""
echo "Pilih aksi:"
echo "  [1] Fresh install - hapus container & volume lama (database kosong)"
echo "  [2] Update / Start - rebuild image, data database tetap"
ACTION="$(ask "Pilihan (1/2, default 2): " "2")"

if [ "$ACTION" = "1" ]; then
    echo ""
    echo "[WARN] PERINGATAN: Fresh install akan MENGHAPUS database lokal (volume mysql)."
    echo "  Data toko harus ditarik ulang dari cloud via sync:bootstrap."
    CONFIRM="$(ask "Yakin? (y/N): " "N")"
    if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
        $COMPOSE_CMD down -v --remove-orphans || true
        echo "[OK] Volume lama dibersihkan."
    else
        echo "Dibatalkan."
        exit 0
    fi
elif [ "$ACTION" != "2" ]; then
    echo "[FAIL] Pilihan tidak valid."
    exit 1
fi

# --------------------------- build -------------------------------------------
echo ""
echo "[STEP] Build image ute-parts-client:latest ..."
docker build -t ute-parts-client:latest -f "$SCRIPT_DIR/Dockerfile" "$PROJECT_ROOT"

echo "[STEP] Menjalankan service app + mysql ..."
$COMPOSE_CMD up -d

# --------------------------- done --------------------------------------------
SERVER_IP=""
if command -v curl >/dev/null 2>&1; then
    SERVER_IP=$(curl -4 -s --max-time 3 https://api.ipify.org || true)
fi
if [ -z "$SERVER_IP" ]; then
    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")
fi

echo ""
echo "============================================================"
echo "   [SUKSES] UTE PARTS ERP CLIENT NODE berjalan!"
echo "============================================================"
echo "Akses lokal        : http://localhost"
echo "Akses LAN / VPS    : http://$SERVER_IP"
echo "Login awal         : $ADMIN_EMAIL / (password di .env.client)"
echo ""
echo "LANGKAH PENTING - tarik data dari cloud:"
echo "  cd \"$SCRIPT_DIR\""
echo "  docker compose exec app php artisan sync:bootstrap"
echo "  docker compose exec -e ADMIN_FORCE=true app php /usr/local/bin/bootstrap-client-admin.php"
echo ""
echo "Cek status sync    : http://localhost/app/sync/status?probe=1"
echo "Log               : $COMPOSE_CMD logs -f app"
echo "Stop              : $COMPOSE_CMD down"
echo "============================================================"
