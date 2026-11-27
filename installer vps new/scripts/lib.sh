#!/usr/bin/env bash
# Helper bersama untuk installer VPS UTEParts.
# Jangan dijalankan sendiri — di-source oleh install.sh.

set -Eeuo pipefail

if [[ -z "${LIB_UTE_LOADED:-}" ]]; then
    LIB_UTE_LOADED=1

    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    NC='\033[0m'

    log()  { printf '%b[INFO] %s%b\n' "$BLUE" "$*" "$NC"; }
    ok()   { printf '%b[ OK ] %s%b\n' "$GREEN" "$*" "$NC"; }
    warn() { printf '%b[WARN] %s%b\n' "$YELLOW" "$*" "$NC"; }
    fail() { printf '%b[FAIL] %s%b\n' "$RED" "$*" "$NC" >&2; exit 1; }

    # ask VAR "Pertanyaan" "default"
    ask() {
        local __var="$1" __prompt="$2" __default="${3:-}" __answer
        if [[ -n "$__default" ]]; then
            read -r -p "$__prompt [$__default]: " __answer
            printf -v "$__var" '%s' "${__answer:-$__default}"
        else
            while [[ -z "${__answer:-}" ]]; do
                read -r -p "$__prompt: " __answer
            done
            printf -v "$__var" '%s' "$__answer"
        fi
    }

    ask_secret() {
        local __var="$1" __prompt="$2" __answer
        read -r -s -p "$__prompt: " __answer
        printf '\n'
        [[ -n "$__answer" ]] || fail "$__prompt tidak boleh kosong."
        printf -v "$__var" '%s' "$__answer"
    }

    confirm() {
        local __prompt="$1" __default="${2:-y}" __answer
        read -r -p "$__prompt [${__default}]: " __answer || true
        __answer="${__answer:-$__default}"
        [[ "$__answer" =~ ^[Yy]$ ]]
    }

    genpass() {
        if command -v openssl >/dev/null 2>&1; then
            openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 40
        else
            tr -dc 'A-Za-z0-9' </dev/urandom | head -c 40
        fi
    }

    ram_mb() {
        awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo
    }

    sed_escape() {
        printf '%s' "$1" | sed -e 's/[\\&|]/\\&/g'
    }

    # render TEMPLATE OUTPUT KEY=VALUE ...
    render() {
        local __src="$1" __dst="$2"
        shift 2
        cp "$__src" "$__dst"
        while [[ $# -ge 2 ]]; do
            local __key="$1" __val="$2"
            shift 2
            sed -i "s|{{${__key}}}|$(sed_escape "$__val")|g" "$__dst"
        done
        if grep -q '{{[A-Z_]*}}' "$__dst"; then
            warn "Placeholder belum terganti di $__dst:"
            grep -o '{{[A-Z_]*}}' "$__dst" | sort -u || true
        fi
    }

    apt_install() {
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
    }

    service_restart() {
        systemctl enable "$1" >/dev/null 2>&1 || true
        systemctl restart "$1"
    }

    command_exists() {
        command -v "$1" >/dev/null 2>&1
    }
fi
