#!/usr/bin/env bash
# ==============================================================================
# Bremspace ERP & POS System — 1-Click Launcher untuk Android Termux
# Brand: Bremspace
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [ -f "${SCRIPT_DIR}/install-termux.sh" ]; then
    bash "${SCRIPT_DIR}/install-termux.sh" "$@"
else
    echo "❌ [ERROR] Berkas ${SCRIPT_DIR}/install-termux.sh tidak ditemukan!"
    exit 1
fi
