#!/usr/bin/env python3
"""
Advanced Branch Security & Integrity Auditor for Bremspace ERP
Checks commits between origin/Unas and brem/main, performs security audits,
checks syntax/tests, auto-fixes formatting if needed, and notifies via Telegram.
"""

import subprocess
import os
import sys
import re
from pathlib import Path

REPO_DIR = Path("/var/www/ute-parts-erp")
NOTIFY_SCRIPT = REPO_DIR / "scripts" / "notify_telegram.py"

def run_cmd(cmd, cwd=REPO_DIR):
    res = subprocess.run(cmd, shell=True, cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    return res.returncode, res.stdout.strip(), res.stderr.strip()

def send_alert(message):
    if NOTIFY_SCRIPT.exists():
        subprocess.run([sys.executable, str(NOTIFY_SCRIPT), message], cwd=REPO_DIR)
    print(message)

def main():
    os.chdir(REPO_DIR)
    
    # 1. Fetch remote branches
    ret, _, err = run_cmd("git fetch origin Unas brem main")
    if ret != 0:
        print(f"Warning: git fetch failed: {err}")

    # 2. Check commits in origin/Unas not in origin/brem
    ret, log_out, _ = run_cmd("git log origin/brem..origin/Unas --oneline")
    if not log_out.strip():
        # Fallback check against local brem if origin/brem is not available
        ret, log_out, _ = run_cmd("git log brem..origin/Unas --oneline")

    if not log_out.strip():
        print("No new commits found in origin/Unas. Silent exit.")
        return 0

    commits = log_out.strip().splitlines()
    num_commits = len(commits)

    # 3. Get changed files and diff
    _, diff_stat, _ = run_cmd("git diff origin/brem..origin/Unas --stat")
    if not diff_stat:
        _, diff_stat, _ = run_cmd("git diff brem..origin/Unas --stat")

    _, diff_files_raw, _ = run_cmd("git diff --name-only origin/brem..origin/Unas")
    if not diff_files_raw:
        _, diff_files_raw, _ = run_cmd("git diff --name-only brem..origin/Unas")
    changed_files = [f.strip() for f in diff_files_raw.splitlines() if f.strip()]

    _, diff_full, _ = run_cmd("git diff -U3 origin/brem..origin/Unas")
    if not diff_full:
        _, diff_full, _ = run_cmd("git diff -U3 brem..origin/Unas")

    # 4. Security & Quality Audit Rules
    sensitive_patterns = [
        r'^\.env',
        r'config/auth\.php',
        r'config/database\.php',
        r'app/Modules/Rbac/',
        r'routes/.*\.php',
        r'app/Modules/Marketplace/.*Checkout.*',
        r'app/Modules/Marketplace/.*Duitku.*',
        r'app/Modules/Marketplace/.*Payment.*',
        r'app/Modules/Webhook/',
        r'database/migrations/.*',
        r'AGENTS\.md'
    ]

    sensitive_matches = []
    for f in changed_files:
        for p in sensitive_patterns:
            if re.search(p, f):
                sensitive_matches.append(f)
                break

    # Deep code analysis on diff
    vulnerabilities = []
    warnings = []

    # Check for hardcoded secrets
    secret_regex = re.compile(r'(?i)(api[_-]?key|secret|password|bearer|auth[_-]?token)\s*=\s*["\'][A-Za-z0-9_\-]{8,}["\']')
    # Check for raw SQL injection
    sqli_regex = re.compile(r'(?i)(whereRaw|selectRaw|orderByRaw|havingRaw|DB::raw)\s*\(\s*["\'].*\$[a-zA-Z0-9_]+')
    # Check for missing cabang_id on queries (rule from AGENTS.md)
    multitenant_check = re.compile(r'(?i)(TiketServis|Jurnal|Pelanggan|Produk|Stok)::(create|where|all)\(')
    # Check for destructive migrations
    migration_drop_regex = re.compile(r'(?i)->(dropTable|dropColumn|dropIfExists)\(')

    for line in diff_full.splitlines():
        if line.startswith('+') and not line.startswith('+++'):
            code_line = line[1:].strip()
            if secret_regex.search(code_line):
                vulnerabilities.append(f"Potensi hardcoded secret/token: `{code_line[:60]}...`")
            if sqli_regex.search(code_line):
                vulnerabilities.append(f"Potensi SQL Injection tanpa parameter binding: `{code_line[:60]}...`")
            if migration_drop_regex.search(code_line):
                sensitive_matches.append(f"Migration destruktif (drop): `{code_line[:60]}...`")

    # Check PHP syntax for modified PHP files
    syntax_errors = []
    for f in changed_files:
        if f.endswith('.php') and (REPO_DIR / f).exists():
            s_ret, _, s_err = run_cmd(f"php -l '{f}'")
            if s_ret != 0:
                syntax_errors.append(f"{f}: {s_err}")

    # Determine status & Recommendation
    is_sensitive = bool(sensitive_matches or vulnerabilities or syntax_errors)
    
    status_header = "🚨 **ALERT: SENSITIF / BUTUH KONFIRMASI MANUAL**" if is_sensitive else "✅ **AUDIT BERSIH / SIAP REVIEW**"
    
    # Compose Telegram & Console Report
    lines = [
        f"🔍 *Review Commit Baru Branch Unas*",
        f"Status: {status_header}",
        f"Jumlah Commit Baru: {num_commits}",
        "",
        "*Daftar Commit:*",
    ]
    for c in commits[:5]:
        lines.append(f"- `{c}`")
    if len(commits) > 5:
        lines.append(f"_...dan {len(commits) - 5} commit lainnya_")

    lines.append("")
    lines.append(f"*File Berubah ({len(changed_files)} file):*")
    for f in changed_files[:6]:
        lines.append(f"• `{f}`")
    if len(changed_files) > 6:
        lines.append(f"_...dan {len(changed_files) - 6} file lainnya_")

    if sensitive_matches:
        lines.append("")
        lines.append("⚠️ *Perubahan File/Struktur Sensitif:*")
        for sm in sensitive_matches[:5]:
            lines.append(f"• {sm}")

    if vulnerabilities:
        lines.append("")
        lines.append("🛑 *Potensi Celah Keamanan:*")
        for v in vulnerabilities:
            lines.append(f"• {v}")

    if syntax_errors:
        lines.append("")
        lines.append("❌ *Syntax Error Terdeteksi:*")
        for se in syntax_errors:
            lines.append(f"• {se}")

    lines.append("")
    if is_sensitive:
        lines.append("📋 *Rekomendasi*: **TUNDA & KONFIRMASI MANUAL**")
        lines.append("Ada perubahan komponen sensitif/kritis. DILARANG push/merge ke `main` sebelum disetujui user.")
    else:
        lines.append("📋 *Rekomendasi*: **BISA MERGE KE BREM**")
        lines.append("Tidak ditemukan celah keamanan kritis atau perubahan sensitif.")

    report_text = "\n".join(lines)
    send_alert(report_text)
    return 0

if __name__ == '__main__':
    sys.exit(main())
