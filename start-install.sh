#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — Universal One-Click Launcher (Linux & macOS)
# Brand: Bremspace
# Target Port: 1741 (Akses Lokal & Jaringan Wi-Fi/LAN)
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Jalankan installer Docker resmi jika tersedia
if [ -f "scripts/install-docker.sh" ]; then
    bash scripts/install-docker.sh "$@"
else
    echo "❌ [ERROR] File scripts/install-docker.sh tidak ditemukan!"
    exit 1
fi
