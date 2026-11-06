#!/usr/bin/env bash
# ==============================================================================
# Script: build-package.sh
# Purpose: Assemble Windows Desktop payload and compile Inno Setup installer
# ==============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
WINDOWS_DIR="${ROOT_DIR}/tools/windows"
PAYLOAD_DIR="${WINDOWS_DIR}/payload"
DIST_DIR="${ROOT_DIR}/dist/windows"
VERSION="${1:-1.0.0}"

echo "========================================================"
echo " Packaging Bremspace ERP Desktop v${VERSION} for Windows"
echo "========================================================"

mkdir -p "${PAYLOAD_DIR}"
mkdir -p "${DIST_DIR}"

# 1. Check binaries
if [ ! -d "${WINDOWS_DIR}/binaries/frankphp" ] || [ ! -d "${WINDOWS_DIR}/binaries/mariadb" ]; then
    echo "==> Binaries missing. Running download-binaries.sh..."
    "${WINDOWS_DIR}/scripts/download-binaries.sh"
fi

# 2. Build Vite Assets
echo "==> Building frontend assets with Vite..."
(cd "${ROOT_DIR}" && npm run build)

# 3. Assemble Application Payload (Exclude dev files)
echo "==> Copying Laravel application code..."
rm -rf "${PAYLOAD_DIR}/app"
mkdir -p "${PAYLOAD_DIR}/app"

rsync -a --exclude='.git' \
         --exclude='node_modules' \
         --exclude='tests' \
         --exclude='storage/logs/*.log' \
         --exclude='storage/framework/cache/data/*' \
         --exclude='storage/framework/sessions/*' \
         --exclude='storage/framework/views/*' \
         --exclude='tools' \
         --exclude='dist' \
         "${ROOT_DIR}/" "${PAYLOAD_DIR}/app/"

# 4. Copy FrankPHP & MariaDB Engines
echo "==> Bundling FrankPHP engine..."
rm -rf "${PAYLOAD_DIR}/engine"
mkdir -p "${PAYLOAD_DIR}/engine"
cp -r "${WINDOWS_DIR}/binaries/frankphp/"* "${PAYLOAD_DIR}/engine/"

echo "==> Bundling MariaDB engine..."
rm -rf "${PAYLOAD_DIR}/db_engine"
mkdir -p "${PAYLOAD_DIR}/db_engine"
cp -r "${WINDOWS_DIR}/binaries/mariadb/"* "${PAYLOAD_DIR}/db_engine/"

# 5. Copy WebView2 Bootstrapper
echo "==> Bundling WebView2 setup..."
mkdir -p "${PAYLOAD_DIR}/webview"
cp "${WINDOWS_DIR}/binaries/webview2/MicrosoftEdgeWebview2Setup.exe" "${PAYLOAD_DIR}/webview/"

# 6. Build Launcher Executable (if Go available)
echo "==> Compiling Launcher Executable..."
if command -v go &>/dev/null; then
    (cd "${WINDOWS_DIR}/launcher" && GOOS=windows GOARCH=amd64 go build -ldflags "-H windowsgui -s -w" -o "${PAYLOAD_DIR}/launcher.exe" main.go)
    echo "    launcher.exe compiled successfully with Go."
else
    echo "    [Notice] Go compiler not found on this system. Creating launcher wrapper..."
    # Fallback placeholder if compiling in environments without Go
    cp "${WINDOWS_DIR}/launcher/launcher.ps1" "${PAYLOAD_DIR}/launcher.ps1"
fi

# 7. Compile with Inno Setup (if iscc available)
echo "==> Checking Inno Setup Compiler (iscc)..."
if command -v iscc &>/dev/null; then
    iscc "/DAppVersion=${VERSION}" "${WINDOWS_DIR}/installer.iss"
    echo "==> Installer successfully created in ${DIST_DIR}/"
else
    echo "    [Info] 'iscc' not found locally. Payload is assembled and ready for GitHub Actions / Windows runner compilation."
fi

echo "==> Package assembly complete!"
