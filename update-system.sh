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
echo "ℹ Memeriksa lisensi perangkat & rilis pembaruan terbaru di GitHub..."

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
