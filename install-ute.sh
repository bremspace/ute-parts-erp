#!/usr/bin/env bash
# ============================================================
# Ute Parts — One-Click Installer untuk CyberPanel + OpenLiteSpeed
#
# Jalankan sebagai ROOT di VPS, SETELAH website dibuat di panel:
#   bash install-ute.sh domain.com
#
# Prasyarat (dikerjakan dulu di CyberPanel UI):
#   1. Websites → Create Website → domain.com, PHP 8.5, Centang Create Database
#      (DB sementara; installer membuat DB sendiri lagi & mengisi .env otomatis)
#   2. DNS A/AAAA domain.com → IP server
#   3. Let's Encrypt via panel (opsional, bisa setelah install)
#
# Yang dikerjakan:
#   - git clone project ke /home/<domain>/public_html
#   - composer install + npm build + storage:link
#   - buat MySQL DB+user + isi .env + migrate + seed
#   - tulis vhost.conf BENAR (docRoot == context /, handler lsapi:lsphp,
#     format flat OLS 1.8) — memperbaiki error 403 "Context / not accessible"
#   - permission benar (user situs milik file, home 711)
#   - validasi lshttpd -t + restart OpenLiteSpeed
# ============================================================
set -euo pipefail

# ---------- Argumen ----------
DOMAIN="${1:?Penggunaan: bash $0 domain.com}"
REPO_GIT="https://github.com/bremspace/ute-parts-erp.git"

# ---------- Deteksi path ----------
HOME_DIR="/home/${DOMAIN}"
WEBROOT="${HOME_DIR}/public_html"          # root project (repo)
DOCROOT="${WEBROOT}/public"                # docRoot Laravel
VHOST_CONF="/usr/local/lsws/conf/vhosts/${DOMAIN}/vhost.conf"

# PHP CLI: ambil lsphp PERTAMA yang cocok pola lsphp85*
PHP_BIN="$(ls /usr/local/lsws/lsphp85/bin/lsphp 2>/dev/null || echo /usr/local/lsws/lsphp*/bin/lsphp)"
# bila glob (banyak), pilih yang berisi "85"
case "$PHP_BIN" in
  *lsphp85*) : ;;                     # sudah cocok
  *) PHP_BIN="$(ls -d /usr/local/lsws/lsphp*80/bin/lsphp /usr/local/lsws/lsphp*81/bin/lsphp /usr/local/lsws/lsphp*82/bin/lsphp /usr/local/lsws/lsphp*83/bin/lsphp /usr/local/lsws/lsphp*84/bin/lsphp /usr/local/lsws/lsphp*85/bin/lsphp 2>/dev/null | sort -V | tail -1)" ;;
esac
[ -x "$PHP_BIN" ] || { echo "❌ PHP binary tidak ditemukan. Cek: ls /usr/local/lsws/"; exit 1; }

# Nama handler LSAPI terdaftar di server (biasanya "lsphp", bukan "lsphp85")
HANDLER="$(grep -oE '^extProcessor[[:space:]]+[A-Za-z0-9_]+' /usr/local/lsws/conf/httpd_config.conf | awk '{print $2}' | head -1)"
[ -n "$HANDLER" ] || HANDLER="lsphp"
echo "ℹ️  PHP CLI : $PHP_BIN"
echo "ℹ️  Handler : lsapi:${HANDLER} (di vhost)"

# ---------- Validasi prasyarat ----------
[ -d "$HOME_DIR" ] || { echo "❌ Website belum dibuat di CyberPanel (folder $HOME_DIR tidak ada). Buat dulu via panel."; exit 1; }
SITE_USER="$(stat -c '%U' "$HOME_DIR")"
[ "$SITE_USER" != "root" ] || SITE_USER="lsadm"
echo "ℹ️  Site user: $SITE_USER | WEBROOT: $WEBROOT"

command -v composer >/dev/null || { echo "❌ composer tidak ada. Install dulu."; exit 1; }
command -v mysql >/dev/null || { echo "❌ mysql client tidak ada."; exit 1; }

# ---------- 1. Clone project ----------
if [ -d "$WEBROOT/.git" ]; then
  echo "✔ Project sudah ada — git pull..."
  cd "$WEBROOT"
  git pull origin main || { git fetch origin && git reset --hard origin/main; }
else
  echo "⟳ Siapkan folder project di $WEBROOT ..."
  # CyberPanel membuat file default (index.html dll) — pindahkan ke backup
  if [ -n "$(ls -A "$WEBROOT" 2>/dev/null)" ]; then
    mkdir -p "${HOME_DIR}/public_html_default_bak"
    cp -a "$WEBROOT"/* "${HOME_DIR}/public_html_default_bak/" 2>/dev/null || true
    cp -a "$WEBROOT"/.[!.]* "${HOME_DIR}/public_html_default_bak/" 2>/dev/null || true
    find "$WEBROOT" -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true
    echo "  ✔ File bawaan CyberPanel di-backup ke public_html_default_bak"
  fi

  echo "⟳ Clone $REPO_GIT ..."
  git clone "$REPO_GIT" "$WEBROOT"
fi

# ---------- 2. Dependency + build ----------
cd "$WEBROOT"
export COMPOSER_ALLOW_SUPERUSER=1
echo "⟳ composer install (--no-dev)..."
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

if command -v node >/dev/null && command -v npm >/dev/null; then
  echo "⟳ npm install + build..."
  (cd "$WEBROOT" && npm install --ignore-scripts --no-audit --no-fund 2>/dev/null && npm run build)
  echo "✔ Build asset selesai."
else
  echo "⚠️ Node/npm tidak ada — lewati build. Asset harus di-upload dulu (public/build)."
fi

# ---------- 3. Database -- isi manual dari CyberPanel ------

echo ""
echo "============================================================"
echo "  LANGKAH 3: DATABASE"
echo "============================================================"
echo ""
echo "  1. CyberPanel: Websites > Manage Database > Create Database"
echo "  2. CATAT: nama database, username, DAN password"
echo "  3. Edit .env: nano WEBROOT/.env"
echo "  4. Isi: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306"
echo "     DB_DATABASE=<nama>, DB_USERNAME=<user>, DB_PASSWORD=<pass>"
echo "  5. Simpan .env"
echo ""
echo "============================================================"
echo ""
read -rp "  Tekan ENTER setelah .env sudah diisi: "
echo ""

# ---------- 4. Key generate ----------
# .env sudah di-edit manual oleh user (step 3), jangan di-overwrite di sini
# Cek .env wajib ada:
if [ ! -f .env ]; then
  cp .env.example .env
  echo "??  .env belum ada, salin dari .env.example � edit dulu sebelum migrate!"
  exit 1
fi
echo "? .env ditemukan. Pastikan DB_* sudah diisi dengan benar."
echo "   CATATAN: APP_KEY akan di-generate otomatis jika kosong."
"$PHP_BIN" artisan key:generate --force 2>/dev/null || true

# ---------- 5. Migrate + seed + cache ----------
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force || echo "⚠️ Seeder gagal sebagian — cek manual nanti."
"$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan config:cache || true
"$PHP_BIN" artisan route:cache || true
"$PHP_BIN" artisan view:cache || true

# ---------- 6. Permission (site user punya file; lsadm bisa lewati) ----------
chown -R "${SITE_USER}:${SITE_USER}" "$WEBROOT"
chmod -R 755 "$WEBROOT"
chmod -R 775 "$WEBROOT/storage" "$WEBROOT/bootstrap/cache"
chmod o+rx /home "$HOME_DIR" 2>/dev/null || true
mkdir -p "${HOME_DIR}/logs"
chown -R "${SITE_USER}:${SITE_USER}" "${HOME_DIR}/logs"
echo "✔ Permission diatur."

# ---------- 7. Tulis vhost.conf BENAR ----------
mkdir -p "/usr/local/lsws/conf/vhosts/${DOMAIN}"
cat > "$VHOST_CONF" <<EOFVHOST
docRoot                   ${DOCROOT}
vhDomain                  ${DOMAIN}
vhAliases                 www.${DOMAIN}
enableGzip                1

index  {
  useServer               0
  indexFiles              index.php index.html
}

errorlog ${HOME_DIR}/logs/error.log {
  useServer               0
  logLevel                DEBUG
  rollingSize             10M
}

accesslog ${HOME_DIR}/logs/access.log {
  useServer               0
  rollingSize             100M
  keepDays                30
  compressArchive         1
}

scripthandler {
  add                     lsapi:${HANDLER} php
}

rewrite {
  enable                  1
  autoLoadHtaccess        1
}

context / {
  location                ${DOCROOT}
  allowBrowse             0
  rewrite {
    enable                1
    autoLoadHtaccess      1
  }
  addDefaultCharset       off
  phpIniOverride {
  }
}
EOFVHOST
echo "✔ vhost.conf ditulis (docRoot == context / == ${DOCROOT})."

# ---------- 8. Validasi + restart ----------
echo "⟳ Validasi config..."
if /usr/local/lsws/bin/lshttpd -t 2>&1 | grep -E "vhost:${DOMAIN}"; then
  echo "⚠️ Masih ada error utk ${DOMAIN}:"
  /usr/local/lsws/bin/lshttpd -t 2>&1 | grep -E "vhost:${DOMAIN}" || true
  echo "👉 Perbaiki lalu: /usr/local/lsws/bin/lshttpd -t && systemctl restart lsws"
else
  echo "✔ Config valid (0 error utk ${DOMAIN})."
  systemctl restart lsws
  echo "✔ OpenLiteSpeed di-restart."
fi

# ---------- 9. Selesai ----------
echo ""
echo "============================================================"
echo "  UTE PARTS — INSTALL SELESAI"
echo "============================================================"
echo "  URL        : https://${DOMAIN}"
echo "  Admin login: /app/login  →  admin@uteparts.com / password"
echo "  DB         : ${DBNAME} / ${DBUSER}"
echo "  .env       : ${WEBROOT}/.env"
echo ""
echo "  Berikutnya (CyberPanel UI):"
echo "  1. Websites → ${DOMAIN} → SSL → Let's Encrypt (aktifkan HTTPS)."
echo "  2. Create internal DNS / pastikan A record ke IP server."
echo ""
echo "  Cek cepat:"
echo "    curl -s -o /dev/null -w '%{http_code}' https://${DOMAIN}/up"
echo "    curl -s -o /dev/null -w '%{http_code}' https://${DOMAIN}/shop"
echo "============================================================"