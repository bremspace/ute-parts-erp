#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP — Script Setup PC Toko (Primary Store Node)
# Modul: Hybrid Database Sync (Offline-Ready & Cloud Failover)
# ADR: 0013 | Target: PC Toko (Linux / Ubuntu / Debian / macOS / WSL)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$PROJECT_ROOT"

echo "======================================================================"
echo "   Bremspace ERP — Inisialisasi PC Toko (Mode Offline-First)"
echo "   Arsitektur Hybrid Sync: Toko Mandiri <-> Cloud VPS"
echo "======================================================================"
echo ""

# 1. Pengecekan Ketersediaan PHP & Ekstensi
echo "ℹ Memeriksa PHP runtime..."
if ! command -v php >/dev/null 2>&1; then
    echo "❌ PHP belum terinstal. Silakan instal PHP 8.2+ terlebih dahulu."
    exit 1
fi

PHP_VER=$(php -r "echo PHP_VERSION;")
echo "✔ PHP terdeteksi: $PHP_VER"

# 2. Pengecekan dan Penyiapan .env
echo ""
echo "ℹ Menyiapkan file konfigurasi lingkungan (.env)..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "✔ Berhasil menyalin .env dari .env.example"
    else
        touch .env
        echo "✔ File .env dibuat baru"
    fi
fi

# Pastikan APP_KEY tersedia
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "ℹ Menghasilkan APP_KEY baru..."
    php artisan key:generate --force
fi

# 3. Konfigurasi Parameter Hybrid Sync
echo ""
echo "----------------------------------------------------------------------"
echo " Konfigurasi Node Hybrid Sync Toko"
echo "----------------------------------------------------------------------"

# Fungsi helper untuk update .env
update_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

update_env "SYNC_ENABLED" "true"
update_env "SYNC_NODE_ROLE" "primary_store"
update_env "SYNC_NODE_ID" "STORE-01"
update_env "CACHE_STORE" "file"
update_env "QUEUE_CONNECTION" "database"

echo "✔ SYNC_ENABLED=true"
echo "✔ SYNC_NODE_ROLE=primary_store"
echo "✔ QUEUE_CONNECTION=database"

# Konfigurasi Remote URL & API Secret jika belum ada
if ! grep -q "^SYNC_REMOTE_URL=" .env || grep -q "^SYNC_REMOTE_URL=$" .env; then
    echo ""
    read -r -p "Masukkan URL Cloud VPS (contoh: https://vps.bremspace.id): " CLOUD_URL_INPUT
    if [ -n "$CLOUD_URL_INPUT" ]; then
        update_env "SYNC_REMOTE_URL" "$CLOUD_URL_INPUT"
        echo "✔ SYNC_REMOTE_URL disetel: $CLOUD_URL_INPUT"
    fi
fi

if ! grep -q "^SYNC_API_SECRET=" .env || grep -q "^SYNC_API_SECRET=$" .env; then
    echo ""
    read -r -p "Masukkan API Secret Sync (sama dengan Cloud VPS): " SHARED_SECRET_INPUT
    if [ -n "$SHARED_SECRET_INPUT" ]; then
        update_env "SYNC_API_SECRET" "$SHARED_SECRET_INPUT"
        echo "✔ SYNC_API_SECRET berhasil disimpan"
    fi
fi

# 4. Migrasi Database Lokal
echo ""
echo "ℹ Menjalankan migrasi database lokal..."
php artisan migrate --force

# 5. Inisialisasi Antrean Sync & Cache
echo "ℹ Membersihkan cache konfigurasi lama..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 6. Pembuatan Script Launcher Desktop Kasir
echo ""
echo "ℹ Membuat launcher cepat kasir toko (jalankan-kasir.sh)..."
cat << 'EOF' > jalankan-kasir.sh
#!/usr/bin/env bash
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

echo "Memulai background queue worker & scheduler..."
mkdir -p storage/logs
php artisan queue:work --queue=default,sync --sleep=3 --tries=3 >> storage/logs/queue-worker.log 2>&1 &
QUEUE_PID=$!
php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
SCHED_PID=$!
trap 'kill $QUEUE_PID $SCHED_PID 2>/dev/null || true' EXIT

echo "Memulai server lokal Bremspace ERP di port 8000..."
php artisan serve --host=0.0.0.0 --port=8000
EOF

chmod +x jalankan-kasir.sh
echo "✔ File jalankan-kasir.sh siap digunakan."

# 7. Penyiapan Cron Job Scheduler
echo ""
echo "======================================================================"
echo "   SETUP SELESAI!"
echo "======================================================================"
echo "Langkah selanjutnya:"
echo "1. Jalankan kasir toko dengan: ./jalankan-kasir.sh"
echo "2. Buka browser di PC kasir: http://localhost:8000/app/pos"
echo "3. Pasang cron job scheduler (opsional, untuk auto-sync tiap menit):"
echo "   * * * * * cd $PROJECT_ROOT && php artisan schedule:run >> /dev/null 2>&1"
echo "======================================================================"
