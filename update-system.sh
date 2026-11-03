#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — One-Click Automated System Updater
# Brand: Bremspace
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================================================"
echo "   Bremspace ERP & POS System — Pembaruan Sistem Otomatis (Updater)"
echo "======================================================================"
echo ""

PHP_BIN="$(command -v php || echo "php")"

# 1. Jalankan pengecekan lisensi & pembaruan via SystemUpdater engine
echo "ℹ Memeriksa lisensi perangkat & rilis pembaruan terbaru..."

# 1. Jalankan pembaruan kontainer Docker jika Docker aktif
if [ -f "compose.yaml" ] && (command -v docker >/dev/null 2>&1 || command -v sudo >/dev/null 2>&1); then
    echo "ℹ Memperbarui kontainer Docker Bremspace ke versi rilis terbaru..."
    docker compose pull 2>/dev/null || sudo docker compose pull 2>/dev/null || true
    docker compose up -d 2>/dev/null || sudo docker compose up -d 2>/dev/null || true
    # Jalankan migrasi database di dalam kontainer app
    docker compose exec -T app php artisan migrate --force 2>/dev/null || sudo docker compose exec -T app php artisan migrate --force 2>/dev/null || true
    docker compose exec -T app php artisan optimize:clear 2>/dev/null || sudo docker compose exec -T app php artisan optimize:clear 2>/dev/null || true
    echo "✔ [OK] Kontainer Docker berhasil diperbarui!"
    echo "======================================================================"
    echo "   Pembaruan Sistem Bremspace Selesai!"
    echo "======================================================================"
    exit 0
fi

$PHP_BIN -r '
require_once "scripts/updater/SystemUpdater.php";
use Scripts\Updater\SystemUpdater;

$status = SystemUpdater::checkForUpdates();
echo "Status: " . $status["message"] . PHP_EOL;

if (!$status["has_update"]) {
    echo "✔ Sistem sudah menggunakan versi terbaru atau update belum diperlukan." . PHP_EOL;
    exit(0);
}

echo "ℹ Memulai proses pengunduhan & instalasi pembaruan..." . PHP_EOL;
$res = SystemUpdater::executeUpdate();
if ($res["success"]) {
    echo "✔ [OK] Pembaruan sistem berhasil diterapkan!" . PHP_EOL;
    echo "Log: " . PHP_EOL . $res["log"] . PHP_EOL;
} else {
    echo "❌ [ERROR] Gagal menerapkan pembaruan: " . $res["log"] . PHP_EOL;
    exit(1);
}
'

echo ""
echo "======================================================================"
echo "   Pembaruan Sistem Bremspace Selesai!"
echo "======================================================================"
