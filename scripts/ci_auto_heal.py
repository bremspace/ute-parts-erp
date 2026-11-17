#!/usr/bin/env python3
"""
Autonomous CI Gate & Auto-Healer for Bremspace ERP
Simulates GitHub Actions CI locally, auto-heals formatting/syntax/minor issues,
and ensures everything is 100% green before any push or merge.
"""

import subprocess
import os
import sys
from pathlib import Path

REPO_DIR = Path("/var/www/ute-parts-erp")
NOTIFY_SCRIPT = REPO_DIR / "scripts" / "notify_telegram.py"

def run_cmd(cmd, env=None, cwd=REPO_DIR):
    merged_env = os.environ.copy()
    if env:
        merged_env.update(env)
    res = subprocess.run(cmd, shell=True, cwd=cwd, env=merged_env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    return res.returncode, res.stdout.strip(), res.stderr.strip()

def send_alert(message):
    if NOTIFY_SCRIPT.exists():
        subprocess.run([sys.executable, str(NOTIFY_SCRIPT), message], cwd=REPO_DIR)
    print(message)

def main():
    os.chdir(REPO_DIR)
    print("🚀 Memulai Autonomous CI Gate & Auto-Heal...")

    healed_items = []
    failed_steps = []

    # 1. Composer validate
    print("-> Checking Composer configuration...")
    ret, out, err = run_cmd("COMPOSER_ALLOW_SUPERUSER=1 composer validate --strict --no-interaction")
    if ret != 0:
        failed_steps.append(f"Composer Validate: {err or out}")
    else:
        print("   [OK] Composer config valid.")

    # 2. Code style check (Pint) & Auto-Heal
    print("-> Checking Code Style (Laravel Pint)...")
    ret, out, err = run_cmd("./vendor/bin/pint --test")
    if ret != 0:
        print("   [WARN] Pint style check failed. Running auto-heal...")
        fix_ret, fix_out, _ = run_cmd("./vendor/bin/pint")
        if fix_ret == 0:
            healed_items.append("Code style diformat otomatis oleh Laravel Pint.")
            print("   [FIXED] Pint auto-heal berhasil.")
        else:
            failed_steps.append("Pint auto-heal gagal.")
    else:
        print("   [OK] Pint code style bersih.")

    # 3. Database migrations (SQLite in-memory)
    print("-> Testing Database Migrations (In-Memory SQLite)...")
    ci_env = {
        'APP_ENV': 'testing',
        'DB_CONNECTION': 'sqlite',
        'DB_DATABASE': ':memory:',
        'CACHE_STORE': 'array',
        'SESSION_DRIVER': 'array',
        'QUEUE_CONNECTION': 'sync'
    }
    ret, out, err = run_cmd("php artisan migrate --force", env=ci_env)
    if ret != 0:
        failed_steps.append(f"Database Migration Error: {err or out}")
    else:
        print("   [OK] Database migrations 100% sukses.")

    # 4. Frontend Asset Compilation (Vite)
    print("-> Testing Frontend Asset Build (Vite)...")
    ret, out, err = run_cmd("npm run build")
    manifest = REPO_DIR / "public" / "build" / "manifest.json"
    if ret != 0 or not manifest.exists():
        failed_steps.append(f"Frontend Build Error: {err or out}")
    else:
        print("   [OK] Frontend assets & manifest.json sukses di-build.")

    # Summary & Reporting
    is_success = len(failed_steps) == 0

    if is_success:
        msg_lines = [
            "✅ *Autonomous CI Gate: 100% HIJAU*",
            "Semua check CI lolos verifikasi lokal:",
            "- Composer config valid",
            "- Laravel Pint style OK",
            "- SQLite In-Memory Migrations OK",
            "- Vite Frontend Build & Manifest OK",
        ]
        if healed_items:
            msg_lines.append("")
            msg_lines.append("*Tindakan Auto-Heal:*")
            for h in healed_items:
                msg_lines.append(f"• {h}")

        msg_lines.append("")
        msg_lines.append("🚀 Kode terverifikasi aman & siap push/merge.")
        send_alert("\n".join(msg_lines))
        return 0
    else:
        msg_lines = [
            "🚨 *Autonomous CI Gate: TERDETEKSI ERROR*",
            "Check yang gagal:",
        ]
        for f in failed_steps:
            msg_lines.append(f"• {f}")
        msg_lines.append("")
        msg_lines.append("⚠️ Push ke main ditahan hingga error diperbaiki.")
        send_alert("\n".join(msg_lines))
        return 1

if __name__ == '__main__':
    sys.exit(main())
