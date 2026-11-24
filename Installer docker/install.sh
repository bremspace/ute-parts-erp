#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Docker Installer (Linux / VPS / Ubuntu / Debian)
# Platform: Linux VPS (Ubuntu 20.04/22.04/24.04, Debian 11/12, CentOS/Rocky)
# Stack   : Docker Compose (App: Apache + PHP 8.4, DB: MySQL 8.0)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$SCRIPT_DIR"

echo "============================================================"
echo "   Bremspace ERP — Docker Installer (Linux VPS / Server)"
echo "============================================================"
echo ""

# 1. Pengecekan Docker & Docker Compose
if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker belum terinstall di Linux ini."
    echo "   Untuk menginstall Docker otomatis di Ubuntu/Debian, jalankan:"
    echo "     curl -fsSL https://get.docker.com | sh"
    echo "     sudo usermod -aG docker \$USER"
    exit 1
fi

COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    echo "❌ Docker Compose belum terinstall."
    echo "   Jalankan: sudo apt-get install docker-compose-plugin"
    exit 1
fi

echo "✔ Docker terdeteksi: $(docker --version)"
echo "✔ Compose terdeteksi: $($COMPOSE_CMD version)"
echo ""

# 2. Pilihan Aksi (Fresh Install vs Update)
echo "Pilih aksi:"
echo "  [1] Fresh install - hapus container & volume lama, mulai bersih"
echo "  [2] Update / Start - build ulang / jalankan container, data database tetap"
read -r -p "Pilihan (1/2, default 2): " ACTION_INPUT
ACTION="${ACTION_INPUT:-2}"

if [ "$ACTION" = "1" ]; then
    echo ""
    echo "⚠ [PERINGATAN] Fresh install akan menghapus database lokal container!"
    read -r -p "Yakin ingin melanjutkan? (y/N): " CONFIRM
    if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo "ℹ Menghentikan dan membersihkan volume lama..."
        $COMPOSE_CMD down -v --remove-orphans || true
        echo "✔ Container lama dibersihkan."
    else
        echo "Dibatalkan."
        exit 0
    fi
fi

# 3. Build & Jalankan Container
echo ""
echo "ℹ Membangun image Docker (Dockerfile)..."
echo ">>> Mem-build Docker image ute-parts-erp:latest..."
docker build -t ute-parts-erp:latest -f "$SCRIPT_DIR/Dockerfile" "$PROJECT_ROOT"

echo "ℹ Menjalankan service app dan database..."
$COMPOSE_CMD up -d

# 4. Deteksi IP Server VPS
SERVER_IP=""
if command -v curl >/dev/null 2>&1; then
    SERVER_IP=$(curl -4 -s --max-time 3 https://api.ipify.org || true)
fi
if [ -z "$SERVER_IP" ]; then
    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")
fi

echo ""
echo "============================================================"
echo "   [SUKSES] Bremspace ERP Docker Berjalan!"
echo "============================================================"
echo "Akses Lokal Server : http://localhost"
echo "Akses Publik / VPS : http://$SERVER_IP"
echo "Untuk melihat log  : cd \"$SCRIPT_DIR\" && $COMPOSE_CMD logs -f app"
echo "Untuk mematikan    : cd \"$SCRIPT_DIR\" && $COMPOSE_CMD down"
echo "============================================================"
