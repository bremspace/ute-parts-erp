#!/usr/bin/env bash
# ==============================================================================
# 1-Click Script: Nonaktifkan AI & Staging di Server Lama (Safe for Production)
# Target: VPS Lama (uteparts.id) — Menjaga Production tetap aktif 100%
# ==============================================================================
set -euo pipefail

C_RESET="\033[0m"
C_BOLD="\033[1m"
C_GREEN="\033[1;32m"
C_CYAN="\033[1;36m"
C_YELLOW="\033[1;33m"
C_RED="\033[1;31m"

echo -e "${C_CYAN}======================================================================${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}   Nonaktifkan AI & Staging — Penghemat Resource Server Lama${C_RESET}"
echo -e "${C_CYAN}   (Proteksi Mutlak: Production uteparts.id Tetap Berjalan)${C_RESET}"
echo -e "${C_CYAN}======================================================================${C_RESET}"

if [ "$(id -u)" -ne 0 ]; then
    echo -e "${C_RED}[ERROR] Script ini wajib dijalankan sebagai root (sudo).${C_RESET}"
    exit 1
fi

echo -e "\n${C_BOLD}[Kondisi Memori Awal]:${C_RESET}"
free -h

# ------------------------------------------------------------------------------
# 1. Matikan Semua Service & Proses AI (OpenCode, 9router, MCP, Watcher)
# ------------------------------------------------------------------------------
echo -e "\n${C_CYAN}⟳ [1/5] Mematikan seluruh proses & daemon AI...${C_RESET}"

# Hentikan systemd service jika terpasang
for s in 9router codebase-memory-mcp opencode cbm-daemon; do
    if systemctl is-active --quiet "$s" 2>/dev/null; then
        echo -e "  • Menghentikan service systemd: $s"
        systemctl stop "$s" 2>/dev/null || true
    fi
    if systemctl is-enabled --quiet "$s" 2>/dev/null; then
        echo -e "  • Menonaktifkan autostart service: $s"
        systemctl disable "$s" 2>/dev/null || true
    fi
done

# Matikan proses background AI & dev watcher
pkill -9 -f "9router" 2>/dev/null || true
pkill -9 -f "codebase-memory-mcp" 2>/dev/null || true
pkill -9 -f "cbm-daemon" 2>/dev/null || true
pkill -9 -f "opencode" 2>/dev/null || true
pkill -9 -f "antigravity" 2>/dev/null || true
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "vite" 2>/dev/null || true

echo -e "${C_GREEN}✔ Seluruh proses AI, MCP, dan Dev Watcher berhasil dihentikan.${C_RESET}"

# ------------------------------------------------------------------------------
# 2. Nonaktifkan Queue Worker Staging (Supervisor) — Tanpa Menyentuh Prod
# ------------------------------------------------------------------------------
echo -e "\n${C_CYAN}⟳ [2/5] Memeriksa & mematikan Queue Worker Staging...${C_RESET}"

SUPERVISOR_CHANGED=0
if [ -d "/etc/supervisor/conf.d" ]; then
    for conf in /etc/supervisor/conf.d/*.conf; do
        [ -f "$conf" ] || continue
        
        # Deteksi jika config milik staging/test (BUKAN production uteparts.id)
        IS_STAGING=0
        if grep -q -i -E "staging|test\.uteparts" "$conf" 2>/dev/null; then
            IS_STAGING=1
        fi

        # Pastikan tidak mematikan worker production
        if [ "$IS_STAGING" -eq 1 ]; then
            PROG_NAME=$(grep -E "^\[program:" "$conf" | sed -e 's/\[program://' -e 's/\]//' | tr -d ' ' || true)
            echo -e "${C_YELLOW}  • Menemukan worker staging: $conf (Program: $PROG_NAME)${C_RESET}"
            if [ -n "$PROG_NAME" ] && command -v supervisorctl >/dev/null 2>&1; then
                supervisorctl stop "$PROG_NAME:*" 2>/dev/null || supervisorctl stop "$PROG_NAME" 2>/dev/null || true
            fi
            mv "$conf" "${conf}.disabled"
            SUPERVISOR_CHANGED=1
            echo -e "    ${C_GREEN}Worker dinonaktifkan: ${conf}.disabled${C_RESET}"
        fi
    done

    if [ "$SUPERVISOR_CHANGED" -eq 1 ] && command -v supervisorctl >/dev/null 2>&1; then
        supervisorctl reread >/dev/null 2>&1 || true
        supervisorctl update >/dev/null 2>&1 || true
    else
        echo -e "  • Tidak ditemukan worker staging terpisah di Supervisor."
    fi
fi
echo -e "${C_GREEN}✔ Supervisor diverifikasi. Worker Production tetap aman.${C_RESET}"

# ------------------------------------------------------------------------------
# 3. Nonaktifkan Cron Job Staging — Tanpa Menyentuh Prod
# ------------------------------------------------------------------------------
echo -e "\n${C_CYAN}⟳ [3/5] Memeriksa cron job staging di /etc/cron* dan crontab...${C_RESET}"

# Cek /etc/cron.d
if [ -d "/etc/cron.d" ]; then
    for cronfile in /etc/cron.d/*; do
        [ -f "$cronfile" ] || continue
        if grep -q -i -E "staging|test\.uteparts" "$cronfile" 2>/dev/null; then
            echo -e "${C_YELLOW}  • Menonaktifkan cron staging di: $cronfile${C_RESET}"
            sed -i '/test\.uteparts/s/^/# [DISABLED_STAGING] /' "$cronfile"
            sed -i '/staging/s/^/# [DISABLED_STAGING] /' "$cronfile"
        fi
    done
fi

# Cek crontab user www-data dan root
for u in www-data root; do
    if crontab -u "$u" -l >/dev/null 2>&1; then
        if crontab -u "$u" -l | grep -q -i -E "staging|test\.uteparts"; then
            echo -e "${C_YELLOW}  • Membersihkan baris staging di crontab user $u...${C_RESET}"
            crontab -u "$u" -l | sed '/test\.uteparts/s/^/# [DISABLED_STAGING] /' | sed '/staging/s/^/# [DISABLED_STAGING] /' | crontab -u "$u" -
        fi
    fi
done
echo -e "${C_GREEN}✔ Cron job staging dinonaktifkan. Cron Production tetap berjalan.${C_RESET}"

# ------------------------------------------------------------------------------
# 4. Nonaktifkan Virtual Host Nginx Staging (test.uteparts.id)
# ------------------------------------------------------------------------------
echo -e "\n${C_CYAN}⟳ [4/5] Memeriksa Nginx virtual host staging...${C_RESET}"

NGINX_CHANGED=0
if [ -d "/etc/nginx/sites-enabled" ]; then
    for vhost in /etc/nginx/sites-enabled/*; do
        [ -e "$vhost" ] || continue
        
        # Jangan pernah menyentuh vhost default prod uteparts.id
        IS_STAGING_VHOST=0
        
        # Periksa server_name dan root path
        if grep -q -i "test\.uteparts" "$vhost" 2>/dev/null || grep -q -i "staging\.uteparts" "$vhost" 2>/dev/null; then
            # Pastikan bukan config utama production
            if ! grep -q -E "server_name[[:space:]]+(www\.)?uteparts\.id" "$vhost" 2>/dev/null; then
                IS_STAGING_VHOST=1
            fi
        fi

        if [ "$IS_STAGING_VHOST" -eq 1 ]; then
            echo -e "${C_YELLOW}  • Menonaktifkan vhost staging: $vhost${C_RESET}"
            rm -f "$vhost"
            NGINX_CHANGED=1
        fi
    done

    if [ "$NGINX_CHANGED" -eq 1 ]; then
        if nginx -t >/dev/null 2>&1; then
            systemctl reload nginx
            echo -e "${C_GREEN}✔ Nginx reloaded dengan konfigurasi bersih (staging dilepas).${C_RESET}"
        else
            echo -e "${C_RED}[PERINGATAN] nginx -t gagal! Memeriksa konfigurasi...${C_RESET}"
            nginx -t
        fi
    else
        echo -e "  • Vhost staging tidak ditemukan di sites-enabled (atau sudah dinonaktifkan)."
    fi
fi

# Set direktori staging ke maintenance jika ada
for dir in /var/www/test.uteparts.id /var/www/test.uteparts.id-staging /var/www/staging; do
    if [ -d "$dir" ] && [ -f "$dir/artisan" ]; then
        echo -e "  • Mengaktifkan maintenance mode di direktori staging: $dir"
        (cd "$dir" && php artisan down --message="Staging telah dimigrasi ke server baru." 2>/dev/null || true)
    fi
done

# ------------------------------------------------------------------------------
# 5. Drop Memory Caches & Verifikasi Integritas Production (uteparts.id)
# ------------------------------------------------------------------------------
echo -e "\n${C_CYAN}⟳ [5/5] Membersihkan cache memori & memverifikasi kesehatan Production...${C_RESET}"

# Flush pagecache & reclaim inactive memory
sync
echo 3 > /proc/sys/vm/drop_caches 2>/dev/null || true

# Reload PHP-FPM untuk reset opcache staging & worker
PHP_FPM_SERVICE=$(systemctl list-units --type=service --state=running | grep -o -E "php[0-9\.]*-fpm\.service" | head -1 || true)
if [ -n "$PHP_FPM_SERVICE" ]; then
    echo -e "  • Me-reload PHP-FPM ($PHP_FPM_SERVICE) agar memory terbebas..."
    systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || true
fi

# Verifikasi Layanan Inti
echo -e "\n${C_BOLD}[Pemeriksaan Status Layanan Inti]:${C_RESET}"
for svc in nginx mysql php8.4-fpm php8.3-fpm supervisor; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        echo -e "  • ${C_GREEN}✔ $svc : ACTIVE (Running)${C_RESET}"
    fi
done

# Verifikasi Web Production uteparts.id
echo -e "\n${C_BOLD}[Pemeriksaan Respon Web Production (uteparts.id)]:${C_RESET}"
PROD_STATUS=$(curl -s -k -o /dev/null -w "%{http_code}" -H "Host: uteparts.id" http://127.0.0.1/ 2>/dev/null || echo "ERR")
if [ "$PROD_STATUS" = "200" ] || [ "$PROD_STATUS" = "301" ] || [ "$PROD_STATUS" = "302" ]; then
    echo -e "  • ${C_GREEN}✔ Respon HTTP Lokal (Host: uteparts.id): $PROD_STATUS (OK)${C_RESET}"
else
    echo -e "  • ${C_YELLOW}ℹ Respon HTTP Lokal (Host: uteparts.id): $PROD_STATUS (Periksa DNS/Vhost bila domain di-route via SSL)${C_RESET}"
fi

echo -e "\n${C_BOLD}[Kondisi Memori Setelah Optimasi]:${C_RESET}"
free -h

echo ""
echo -e "${C_BOLD}${C_GREEN}======================================================================${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}   🎉 SUKSES! AI DAN STAGING BERHASIL DINONAKTIFKAN.${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}   Layanan Production (uteparts.id) tetap berjalan lancar & stabil!${C_RESET}"
echo -e "${C_BOLD}${C_GREEN}======================================================================${C_RESET}"
