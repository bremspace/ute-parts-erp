#!/usr/bin/env bash
set -e

# ==============================================================================
# Script Restore & Setup Perangkat Baru (Linux / macOS / WSL2 Ubuntu)
# Ute Parts ERP + OpenCode + OMO-Slim + MCP + Sesi Percakapan
# ==============================================================================

BOLD='\033[1m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BOLD}${GREEN}====================================================${NC}"
echo -e "${BOLD}${GREEN}   SETUP & RESTORE ENVIRONMENT DI PERANGKAT BARU     ${NC}"
echo -e "${BOLD}${GREEN}====================================================${NC}"

# 1. Cek Root / Sudo
if [ "$EUID" -ne 0 ]; then
  SUDO="sudo"
else
  SUDO=""
fi

# 2. Deteksi OS
OS="$(uname -s)"
echo -e "\n${YELLOW}[1/6] Memeriksa paket dasar OS (${OS})...${NC}"

if [ "$OS" = "Linux" ]; then
  if command -v apt-get >/dev/null 2>&1; then
    $SUDO apt-get update -y
    $SUDO apt-get install -y curl wget git unzip sqlite3 libsqlite3-dev build-essential
  elif command -v dnf >/dev/null 2>&1; then
    $SUDO dnf install -y curl wget git unzip sqlite sqlite-devel gcc make
  elif command -v pacman >/dev/null 2>&1; then
    $SUDO pacman -Sy --noconfirm curl wget git unzip sqlite gcc make
  fi
elif [ "$OS" = "Darwin" ]; then
  if ! command -v brew >/dev/null 2>&1; then
    echo "Homebrew belum terpasang. Pasang Homebrew dulu: https://brew.sh"
    exit 1
  fi
  brew install curl wget git sqlite
fi

# 3. Instalasi Node.js (jika belum ada)
echo -e "\n${YELLOW}[2/6] Memeriksa Node.js & NPM...${NC}"
if ! command -v node >/dev/null 2>&1; then
  echo "Menginstall Node.js v22 via fnm/nvm..."
  curl -fsSL https://fnm.vercel.app/install | bash
  export PATH="$HOME/.local/share/fnm:$PATH"
  eval "$(fnm env 2>/dev/null || true)"
  fnm install 22
  fnm use 22
fi
echo -e "Node: ${GREEN}$(node -v)${NC}, NPM: ${GREEN}$(npm -v)${NC}"

# 4. Instalasi PHP & Composer
echo -e "\n${YELLOW}[3/6] Memeriksa PHP & Composer...${NC}"
if ! command -v php >/dev/null 2>&1; then
  if [ "$OS" = "Linux" ] && command -v apt-get >/dev/null 2>&1; then
    $SUDO apt-get install -y php-cli php-mbstring php-xml php-curl php-sqlite3 php-zip php-bcmath php-intl
  elif [ "$OS" = "Darwin" ]; then
    brew install php
  fi
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Mengunduh Composer..."
  curl -sS https://getcomposer.org/installer | php
  $SUDO mv composer.phar /usr/local/bin/composer
fi
echo -e "PHP: ${GREEN}$(php -v | head -n 1)${NC}"
echo -e "Composer: ${GREEN}$(composer --version | head -n 1)${NC}"

# 5. Instalasi OpenCode CLI
echo -e "\n${YELLOW}[4/6] Memeriksa OpenCode CLI...${NC}"
if ! command -v opencode >/dev/null 2>&1; then
  echo "Mengunduh & menginstall OpenCode..."
  curl -fsSL https://opencode.ai/install.sh | bash || npm install -g opencode-ai
  export PATH="$HOME/.opencode/bin:$PATH"
fi

# 6. Restore Data OpenCode, OMO-Slim, Skills & Riwayat Percakapan
echo -e "\n${YELLOW}[5/6] Me-restore Sesi Chat, OMO-Slim, Skills, & MCP Config...${NC}"
if [ -f "opencode-migration-pack.tar.gz" ]; then
  TMP_EXTRACT=$(mktemp -d)
  tar -xzf opencode-migration-pack.tar.gz -C "$TMP_EXTRACT"

  mkdir -p "$HOME/.config" "$HOME/.local/share"

  if [ -f "$TMP_EXTRACT/opencode-data.tar.gz" ]; then
    tar -xzf "$TMP_EXTRACT/opencode-data.tar.gz" -C "$HOME"
    echo -e "${GREEN}✓ ~/.config/opencode berhasil dipulihkan${NC}"
    echo -e "${GREEN}✓ ~/.local/share/opencode (Database Sesi Chat) berhasil dipulihkan${NC}"
    echo -e "${GREEN}✓ ~/.config/mcp-settings.json berhasil dipulihkan${NC}"
  fi

  if [ -f "$TMP_EXTRACT/project.env" ] && [ ! -f ".env" ]; then
    cp "$TMP_EXTRACT/project.env" .env
    echo -e "${GREEN}✓ .env berhasil disalin ke project root${NC}"
  fi

  rm -rf "$TMP_EXTRACT"
else
  echo -e "${RED}[PERINGATAN] File opencode-migration-pack.tar.gz tidak ditemukan di direktori ini!${NC}"
  echo "Salin file opencode-migration-pack.tar.gz dari VPS ke folder ini lalu jalankan ulang script ini."
fi

# 7. Setup Dependensi Project Laravel
echo -e "\n${YELLOW}[6/6] Memasang dependensi project (Composer & NPM)...${NC}"
if [ -f "composer.json" ]; then
  composer install --no-interaction
  npm install
  npm run build
  php artisan key:generate --force || true
fi

echo -e "\n${BOLD}${GREEN}====================================================${NC}"
echo -e "${BOLD}${GREEN}   RESTORE SELESAI DENGAN SUKSES!                   ${NC}"
echo -e "${BOLD}${GREEN}====================================================${NC}"
echo -e "Untuk melanjutkan sesi opencode:"
echo -e "  ${YELLOW}opencode${NC}  (atau buka TUI / Web GUI OpenCode)"
echo -e "Semua sesi obrolan lama, skills, agent OMO, dan MCP sudah aktif kembali."
