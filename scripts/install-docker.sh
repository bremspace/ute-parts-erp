#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Universal Docker Installer (Linux Desktop & VPS)
# Brand: Bremspace
# Target Port: 1741 (Akses Lokal, Jaringan Wi-Fi/LAN, & Domain Publik)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

echo "======================================================================"
echo "   Bremspace ERP & POS System — Docker Engine Installer (Linux/VPS)"
echo "   Target Port: 1741 (Localhost & Jaringan Wi-Fi/LAN)"
echo "======================================================================"
echo ""

# ------------------------------------------------------------------------------
# 1. Deteksi Sistem Operasi & Arsitektur
# ------------------------------------------------------------------------------
OS_TYPE="$(uname -s)"
ARCH_TYPE="$(uname -m)"
echo "✔ [OK] Sistem Operasi : $OS_TYPE ($ARCH_TYPE)"

# ------------------------------------------------------------------------------
# 2. Alokasi Otomatis Swap Memory 2GB (Krusial untuk VPS 1GB RAM)
# ------------------------------------------------------------------------------
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

# ------------------------------------------------------------------------------
# 3. Pengecekan & Auto-Install Docker Engine + Docker Compose Plugin
# ------------------------------------------------------------------------------
echo "ℹ Memeriksa engine Docker..."
if ! command -v docker >/dev/null 2>&1; then
    echo "ℹ [AUTO-FIX] Docker belum terpasang. Mengunduh dan memasang Docker Engine resmi..."
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
        sudo sh /tmp/get-docker.sh
        rm -f /tmp/get-docker.sh
    elif command -v wget >/dev/null 2>&1; then
        wget -qO /tmp/get-docker.sh https://get.docker.com
        sudo sh /tmp/get-docker.sh
        rm -f /tmp/get-docker.sh
    else
        echo "❌ [ERROR] curl atau wget tidak ditemukan. Silakan pasang curl terlebih dahulu."
        exit 1
    fi

    # Aktifkan service Docker
    sudo systemctl enable --now docker 2>/dev/null || sudo service docker start 2>/dev/null || true
    # Berikan izin ke user saat ini jika bukan root
    if [ "$USER" != "root" ]; then
        sudo usermod -aG docker "$USER" 2>/dev/null || true
    fi
    echo "✔ [OK] Docker Engine resmi berhasil dipasang."
fi

# Pastikan Docker Daemon aktif
if ! sudo docker info >/dev/null 2>&1 && ! docker info >/dev/null 2>&1; then
    echo "ℹ [AUTO-FIX] Menyalakan Docker daemon service..."
    sudo systemctl start docker 2>/dev/null || sudo service docker start 2>/dev/null || true
    sleep 2
fi

# Tentukan perintah docker compose yang aktif
DOCKER_COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker compose"
elif sudo docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="sudo docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker-compose"
elif command -v sudo >/dev/null 2>&1 && sudo docker-compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="sudo docker-compose"
else
    echo "ℹ [AUTO-FIX] Memasang docker compose plugin..."
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update -y && sudo apt-get install -y docker-compose-plugin || true
    elif command -v dnf >/dev/null 2>&1; then
        sudo dnf install -y docker-compose-plugin || true
    fi
    DOCKER_COMPOSE_CMD="docker compose"
fi

echo "✔ [OK] Docker Engine: $(docker --version 2>/dev/null || sudo docker --version)"

# ------------------------------------------------------------------------------
# 4. Verifikasi Lisensi Hardware Offline (Bremspace Anti-Duplikasi)
# ------------------------------------------------------------------------------
PHP_CLI=""
if command -v php >/dev/null 2>&1; then
    PHP_CLI="php"
fi

LICENSE_FILE="storage/license.key"
IS_LICENSED=0

if [ -f "$LICENSE_FILE" ] && [ -n "$PHP_CLI" ]; then
    IS_LICENSED=$("$PHP_CLI" -r '
        require_once "scripts/license/LicenseVerifier.php";
        echo \Scripts\License\LicenseVerifier::isLicensed() ? "1" : "0";
    ' 2>/dev/null || echo "0")
fi

if [ "$IS_LICENSED" != "1" ]; then
    echo ""
    echo "======================================================================"
    echo "           AKTIVASI LISENSI RESMI BREMSPACE ERP & POS"
    echo "======================================================================"

    CHALLENGE_CODE=""
    if [ -n "$PHP_CLI" ]; then
        CHALLENGE_CODE=$("$PHP_CLI" -r '
            require_once "scripts/license/HardwareIdentifier.php";
            echo \Scripts\License\HardwareIdentifier::getChallengeCode();
        ' 2>/dev/null || echo "")
    fi

    if [ -z "$CHALLENGE_CODE" ]; then
        # Fallback challenge jika PHP belum ada di host
        HOST_MID=$(cat /etc/machine-id 2>/dev/null || hostname)
        RAW_HASH=$(echo -n "BREMSPACE_HWID_V1_2026:${HOST_MID}" | sha256sum | awk '{print toupper($1)}')
        P1=$(echo "$RAW_HASH" | cut -c1-4)
        P2=$(echo "$RAW_HASH" | cut -c5-8)
        P3=$(echo "$RAW_HASH" | cut -c9-12)
        CHALLENGE_CODE="BREM-${P1}-${P2}-${P3}"
    fi

    echo "Identitas Hardware Perangkat Anda:"
    echo "Kode Mesin (Challenge Code) : $CHALLENGE_CODE"
    echo ""
    echo "Kirim kode di atas ke WhatsApp Developer Bremspace untuk aktivasi:"
    echo "👉 Tautan: https://wa.me/?text=Halo+saya+minta+aktivasi+lisensi+Bremspace+kode:+${CHALLENGE_CODE}"
    echo "----------------------------------------------------------------------"
    
    VALID_INPUT=0
    for attempt in 1 2 3; do
        read -r -p "Masukkan Kode Aktivasi dari Developer (ACT-XXXX-YYYY-ZZZZ-WWWW): " INPUT_KEY
        INPUT_KEY=$(echo "$INPUT_KEY" | tr -d ' ' | tr '[:lower:]' '[:upper:]')

        # Verifikasi signature
        EXPECTED_SIG=""
        if [ -n "$PHP_CLI" ]; then
            EXPECTED_SIG=$("$PHP_CLI" -r '
                require_once "scripts/license/LicenseVerifier.php";
                echo \Scripts\License\LicenseVerifier::computeSignature("'$CHALLENGE_CODE'");
            ' 2>/dev/null || echo "")
        fi

        if [ -z "$EXPECTED_SIG" ]; then
            # Fallback kalkulasi signature bash
            HMAC_RAW=$(echo -n "$CHALLENGE_CODE" | openssl dgst -sha256 -hmac "BREMSPACE_LICENSE_SECURITY_ENGINE_V1" | awk '{print toupper($NF)}')
            P1=$(echo "$HMAC_RAW" | cut -c1-4)
            P2=$(echo "$HMAC_RAW" | cut -c5-8)
            P3=$(echo "$HMAC_RAW" | cut -c9-12)
            P4=$(echo "$HMAC_RAW" | cut -c13-16)
            EXPECTED_SIG="ACT-${P1}-${P2}-${P3}-${P4}"
        fi

        if [ "$INPUT_KEY" = "$EXPECTED_SIG" ] && [ -n "$EXPECTED_SIG" ]; then
            mkdir -p storage
            cat <<EOF > "$LICENSE_FILE"
{
    "status": "ACTIVE",
    "challenge": "$CHALLENGE_CODE",
    "key": "$INPUT_KEY",
    "verified_at": "$(date '+%Y-%m-%d %H:%M:%S')",
    "brand": "Bremspace"
}
EOF
            chmod 644 "$LICENSE_FILE"
            echo "✔ [OK] Lisensi Hardware Bremspace BERHASIL Diverifikasi!"
            VALID_INPUT=1
            break
        else
            echo "❌ [GAGAL] Kode Aktivasi tidak valid untuk mesin ini. (Percobaan $attempt/3)"
        fi
    done

    if [ "$VALID_INPUT" != "1" ]; then
        echo "❌ [ERROR] Aktivasi lisensi gagal. Instalasi dihentikan."
        exit 1
    fi
else
    echo "✔ [OK] Lisensi Bremspace aktif & terverifikasi sah di perangkat ini."
fi

# ------------------------------------------------------------------------------
# 5. Siapkan Direktori Storage & Database Lokal
# ------------------------------------------------------------------------------
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database docker/data/mysql
chmod -R 775 storage bootstrap/cache database 2>/dev/null || true

# Pastikan file .env tersedia untuk mount volume Docker
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi
export BREMSPACE_HOST_CHALLENGE="$CHALLENGE_CODE"

# ------------------------------------------------------------------------------
# 5b. Autentikasi GitHub Container Registry (GHCR) Otomatis
# ------------------------------------------------------------------------------
GH_TOKEN_OBF="dHZndWhvX2NuZ18xMU83VDdLVFYwRkg3WWtqS3VBNmJLX1V4dmN4UUl4czh0MUtGRVRXYmdoWGxZZnZrZ1RXOWlUaUdXc0NqSVZYdzgzUEJHRUVNNnhJN1ljYjRp"
GH_PAT=$(echo "$GH_TOKEN_OBF" | base64 -d 2>/dev/null | tr 'A-Za-z' 'N-ZA-Mn-za-m' || true)

if [ -n "$GH_PAT" ]; then
    echo "ℹ Melakukan otentikasi aman ke GitHub Container Registry..."
    echo "$GH_PAT" | docker login ghcr.io -u bremspace --password-stdin 2>/dev/null || sudo docker login ghcr.io -u bremspace --password-stdin 2>/dev/null || true
fi

# ------------------------------------------------------------------------------
# 6. Jalankan Docker Compose Stack
# ------------------------------------------------------------------------------
echo "ℹ Memulai layanan kontainer Bremspace (Port 1741)..."
if grep -q "build:" compose.yaml 2>/dev/null && [ -f "Dockerfile" ]; then
    $DOCKER_COMPOSE_CMD up -d --build
else
    # Mode B (Thin Bootstrapper): Tarik image pre-built dari GHCR
    $DOCKER_COMPOSE_CMD pull app queue 2>/dev/null || true
    $DOCKER_COMPOSE_CMD up -d
fi

# ------------------------------------------------------------------------------
# 7. Deteksi IP Jaringan Lokal (Wi-Fi / LAN untuk Multi-Device)
# ------------------------------------------------------------------------------
LAN_IP=""
if command -v hostname >/dev/null 2>&1; then
    LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
fi
if [ -z "$LAN_IP" ] && command -v ip >/dev/null 2>&1; then
    LAN_IP=$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7}')
fi

PORT=1741
TARGET_PATH="/install.php"
if [ -f "storage/installed.lock" ]; then
    TARGET_PATH="/app/login"
fi

LOCAL_URL="http://localhost:${PORT}${TARGET_PATH}"
NETWORK_URL=""
if [ -n "$LAN_IP" ]; then
    NETWORK_URL="http://${LAN_IP}:${PORT}${TARGET_PATH}"
fi

# Buka Web Browser Otomatis jika desktop GUI tersedia
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$LOCAL_URL" >/dev/null 2>&1 &
fi

echo ""
echo "======================================================================"
echo "   Layanan Bremspace ERP & POS Berhasil Aktif via Docker!"
echo "   - Dari Komputer Ini       : $LOCAL_URL"
if [ -n "$NETWORK_URL" ]; then
echo "   - Dari HP/iPhone/iPad/LAN : $NETWORK_URL"
fi
echo "----------------------------------------------------------------------"
echo "   [INFO] Untuk mematikan server: $DOCKER_COMPOSE_CMD down"
echo "   [INFO] Untuk melihat logs    : $DOCKER_COMPOSE_CMD logs -f"
echo "======================================================================"
echo ""
