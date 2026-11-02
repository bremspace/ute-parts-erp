#!/usr/bin/env bash
# ==============================================================================
# EMERGENCY DATABASE RESTORE — ERP & POS SYSTEM
# Jalankan ini jika database hilang/rusak dan tidak ada akses ke UI maupun OpenCode.
# Penggunaan: sudo bash emergency-restore.sh
# ==============================================================================
set -euo pipefail

C_GREEN="\033[1;32m"
C_RED="\033[1;31m"
C_YELLOW="\033[1;33m"
C_RESET="\033[0m"

echo -e "${C_YELLOW}⟳ [1/4] Memeriksa & Menyalakan Service MySQL...${C_RESET}"
systemctl start mysql 2>/dev/null || systemctl start mariadb 2>/dev/null || true

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${SCRIPT_DIR}/storage/app/backups"

echo -e "${C_YELLOW}⟳ [2/4] Mencari file backup terbaru di ${BACKUP_DIR}...${C_RESET}"
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${C_RED}❌ Folder backup tidak ditemukan di: ${BACKUP_DIR}${C_RESET}"
    exit 1
fi

LATEST_BACKUP=$(ls -t "${BACKUP_DIR}"/backup-*.sql.gz 2>/dev/null | head -n 1 || true)
if [ -z "$LATEST_BACKUP" ]; then
    echo -e "${C_RED}❌ Tidak ditemukan file backup (.sql.gz) di folder ${BACKUP_DIR}${C_RESET}"
    exit 1
fi

echo -e "  • Ditemukan backup: ${C_GREEN}$(basename "$LATEST_BACKUP")${C_RESET} ($(du -h "$LATEST_BACKUP" | cut -f1))"

# Ambil DB_DATABASE dari .env jika ada
DB_NAME="db_staging"
if [ -f "${SCRIPT_DIR}/.env" ]; then
    PARSED_DB=$(grep -E "^DB_DATABASE=" "${SCRIPT_DIR}/.env" | cut -d= -f2 | tr -d '\"' | tr -d "'" || true)
    if [ -n "$PARSED_DB" ]; then
        DB_NAME="$PARSED_DB"
    fi
fi

echo -e "${C_YELLOW}⟳ [3/4] Menyiapkan database '${DB_NAME}'...${C_RESET}"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || \
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo -e "${C_YELLOW}⟳ [4/4] Mengimpor data ke database '${DB_NAME}'...${C_RESET}"
zcat "$LATEST_BACKUP" | (mysql "$DB_NAME" 2>/dev/null || sudo mysql "$DB_NAME")

TOTAL_TABLES=$(mysql -Nse "SELECT count(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || \
sudo mysql -Nse "SELECT count(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';")

echo ""
echo -e "${C_GREEN}══════════════════════════════════════════════════════════════════════════════${C_RESET}"
echo -e "${C_GREEN}✔ PEMULIHAN DATABASE SELESAI! (${TOTAL_TABLES} Tabel Berhasil Dipulihkan)${C_RESET}"
echo -e "${C_GREEN}══════════════════════════════════════════════════════════════════════════════${C_RESET}"
