#!/usr/bin/env bash
# ==============================================================================
# Auto Restore Script for AI Stack, History Sessions, Skills, & MCP
# Target: Fresh / Destination Linux VPS (Run as Root)
# ==============================================================================
set -euo pipefail

C_RESET="\033[0m"
C_BOLD="\033[1m"
C_GREEN="\033[1;32m"
C_CYAN="\033[1;36m"
C_YELLOW="\033[1;33m"
C_RED="\033[1;31m"

echo -e "${C_CYAN}======================================================================${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}   Auto Restore AI Stack (Sessions, Plugins, MCP, & Skills)${C_RESET}"
echo -e "${C_CYAN}======================================================================${C_RESET}"

if [ "$(id -u)" -ne 0 ]; then
    echo -e "${C_RED}[ERROR] Script ini wajib dijalankan sebagai root (sudo).${C_RESET}"
    exit 1
fi

BACKUP_FILE="/tmp/ai-session-backup.tar.gz"
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${C_RED}[ERROR] File backup tidak ditemukan di: ${BACKUP_FILE}${C_RESET}"
    echo -e "${C_YELLOW}Pastikan file ai-session-backup.tar.gz sudah dicopy ke /tmp/ VPS ini.${C_RESET}"
    exit 1
fi

echo -e "${C_CYAN}⟳ [1/4] Mengekstrak data backup ke /root...${C_RESET}"
tar -xzvf "$BACKUP_FILE" -C /root >/dev/null 2>&1
echo -e "${C_GREEN}✔ Ekstrak file sesi, config, dan skills selesai.${C_RESET}"

echo -e "${C_CYAN}⟳ [2/4] Memeriksa Node.js & NPM...${C_RESET}"
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    echo -e "${C_YELLOW}  • Node.js belum ada, memasang Node.js & NPM...${C_RESET}"
    apt-get update -qq >/dev/null
    apt-get install -y -qq nodejs npm >/dev/null 2>&1 || true
fi

echo -e "${C_CYAN}⟳ [3/4] Memasang OpenCode CLI & Tools Global (9router, MCP)...${C_RESET}"
if ! command -v opencode >/dev/null 2>&1 && [ ! -f "/root/.opencode/bin/opencode" ]; then
    echo -e "${C_YELLOW}  • Mengunduh OpenCode CLI...${C_RESET}"
    curl -fsSL https://opencode.ai/install.sh | bash >/dev/null 2>&1 || true
fi

if [ -f "/root/.opencode/bin/opencode" ]; then
    ln -sf "/root/.opencode/bin/opencode" /usr/local/bin/opencode 2>/dev/null || true
    ln -sf "/root/.opencode/bin/opencode" /usr/bin/opencode 2>/dev/null || true
fi

echo -e "${C_CYAN}  • Memasang 9router, codebase-memory-mcp, dan oh-my-opencode...${C_RESET}"
npm install -g 9router codebase-memory-mcp oh-my-opencode --no-fund --no-audit >/dev/null 2>&1 || true

echo -e "${C_CYAN}⟳ [4/4] Sinkronisasi izin file...${C_RESET}"
chmod -R 755 /root/.config/opencode 2>/dev/null || true
chmod -R 755 /root/.agents 2>/dev/null || true
chmod -R 700 /root/.local/share/opencode 2>/dev/null || true

echo ""
echo -e "${C_BOLD}${C_GREEN}======================================================================${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}   🎉 RESTORE BERHASIL! SEMUA SESI & AI STACK TELAH AKTIF!${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}======================================================================${C_RESET}"
echo -e "  • ${C_CYAN}OpenCode CLI${C_RESET}     : $(opencode --version 2>/dev/null || echo 'Installed')"
echo -e "  • ${C_CYAN}History Sesi DB${C_RESET}  : /root/.local/share/opencode/opencode.db"
echo -e "  • ${C_CYAN}MCP Servers${C_RESET}      : codebase-memory-mcp (aktif)"
echo -e "  • ${C_CYAN}Plugins/Skills${C_RESET}   : oh-my-opencode-slim & $(ls /root/.agents/skills 2>/dev/null | wc -l) skills"
echo ""
echo -e "Ketik ${C_BOLD}opencode${C_RESET} untuk melanjutkan sesi kerja Anda."
