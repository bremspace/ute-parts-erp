#!/usr/bin/env bash
# Pasang Docker Compose v2 (plugin) atau fallback binary standalone.
# Idempoten: aman dijalankan berulang kali.
set -euo pipefail

ARCH="$(uname -m)"
case "$ARCH" in
  x86_64|amd64) COMPOSE_ARCH="x86_64" ;;
  aarch64|arm64) COMPOSE_ARCH="aarch64" ;;
  *) echo "Arsitektur tidak didukung: $ARCH" >&2; exit 1 ;;
esac

# Versi compose terbaruk yang stabil; bisa di-override lewat COMPOSE_VERSION.
COMPOSE_VERSION="${COMPOSE_VERSION:-v2.39.4}"

if docker compose version >/dev/null 2>&1; then
  echo "docker compose sudah ada: $(docker compose version)"
  exit 0
fi

# 1) Coba pasang sebagai plugin CLI Docker.
PLUGIN_DIR="/usr/local/lib/docker/cli-plugins"
if mkdir -p "$PLUGIN_DIR" 2>/dev/null && [ -w "$PLUGIN_DIR" ]; then
  URL="https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-${COMPOSE_ARCH}"
  echo "Mengunduh ${URL}"
  if curl -fsSL "$URL" -o "$PLUGIN_DIR/docker-compose"; then
    chmod +x "$PLUGIN_DIR/docker-compose"
    docker compose version && exit 0
  fi
  echo "Gagal memasang plugin, mencoba binary standalone..." >&2
fi

# 2) Fallback: binary standalone di /usr/local/bin/docker-compose.
URL="https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-${COMPOSE_ARCH}"
echo "Mengunduh ${URL}"
curl -fsSL "$URL" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
docker-compose version
