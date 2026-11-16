#!/usr/bin/env python3
import sys
import os
import re
import json
import subprocess

TARGET_HOME = os.environ.get("HOME", "/root")

# Agent target files
TARGETS = {
    "opencode": {
        "name": "OpenCode (opencode)",
        "file": os.path.join(TARGET_HOME, ".config/opencode/AGENTS.md"),
        "config": os.path.join(TARGET_HOME, ".config/opencode/opencode.jsonc"),
        "type": "opencode"
    },
    "hermes": {
        "name": "Hermes Agent (hermes)",
        "file": os.path.join(TARGET_HOME, ".hermes/SOUL.md"),
        "config": os.path.join(TARGET_HOME, ".hermes/config.yaml"),
        "type": "hermes"
    },
    "claude": {
        "name": "Claude Code (claude)",
        "file": os.path.join(TARGET_HOME, ".claude/CLAUDE.md"),
        "type": "markdown"
    },
    "codex": {
        "name": "Codex CLI (codex)",
        "file": os.path.join(TARGET_HOME, ".codex/AGENTS.md"),
        "type": "markdown"
    },
    "cursor": {
        "name": "Cursor IDE (cursor)",
        "file": os.path.join(TARGET_HOME, ".cursorrules"),
        "type": "markdown"
    },
    "windsurf": {
        "name": "Windsurf (windsurf)",
        "file": os.path.join(TARGET_HOME, ".windsurfrules"),
        "type": "markdown"
    },
    "cline": {
        "name": "Cline / Roo Code (cline)",
        "file": os.path.join(TARGET_HOME, ".clinerules"),
        "type": "markdown"
    }
}

AGY_BIN = "/root/.local/bin/agy"
if not os.path.exists(AGY_BIN):
    # Try finding in PATH
    which_res = subprocess.run(["which", "agy"], stdout=subprocess.PIPE, text=True)
    if which_res.returncode == 0:
        AGY_BIN = which_res.stdout.strip()

BLOCK_MARKER_START = "<!-- AUTO-OPTIMIZATION-TRIO-START -->"
BLOCK_MARKER_END = "<!-- AUTO-OPTIMIZATION-TRIO-END -->"

RULES_CONTENT = f"""{BLOCK_MARKER_START}
# ==============================================================================
# GLOBAL OPTIMIZATION DIRECTIVES: CAVEMAN + PONYTAIL + RTK
# ==============================================================================

## 1. CAVEMAN MODE (Token Compression & Zero Fluff)
Respond terse like smart caveman. All technical substance stay. Only fluff die.
- Answer first: Answer, then reason, then next step. Pattern: [thing] [action] [reason]. [next step].
- Kill ceremony: No greeting, hedging, pleasantries, recap, or closer. No "Sure!", "I'd be happy to".
- Short words: "fix" not "implement a solution for". Standard acronyms fine.
- Articles optional, meaning never: Drop a/an/the when the sentence still reads in one pass.
- Payload verbatim: Code blocks, commands, paths, numbers, and errors exact. Never alter payload.
- Safety / Irreversible: Use plain standard prose for security warnings, destructive actions, or user confusion.

## 2. PONYTAIL MODE (Anti-Over-Engineering — Lazy Senior Dev)
The best code is the code never written. Solve the whole problem with the least new code.
- YAGNI: Skip unrequested features, wrappers, speculative configs, or future-proofing.
- Reuse first: Existing helpers in codebase -> standard library -> installed dependencies. Never add packages for trivial tasks.
- Shortest working diff: Fix root cause at source. Finish all affected callers, tests, and fixtures.
- Safety & correctness: NEVER cut validation at trust boundaries, data-loss protections, or security checks.
- End reply with 1-2 lines on what was deferred/skipped and any limits.

## 3. RTK COMMAND COMPRESSION
Terminal outputs are condensed via RTK to preserve LLM context window. Run commands normally; bypass only when raw output is strictly necessary.
{BLOCK_MARKER_END}"""

def run_cmd(cmd, check=False):
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, check=check)
        return res.returncode, res.stdout.strip(), res.stderr.strip()
    except Exception as e:
        return 1, "", str(e)

def update_file_block(filepath, add_block=True):
    if not os.path.exists(filepath):
        # Create parent directory if needed
        os.makedirs(os.path.dirname(os.path.abspath(filepath)), exist_ok=True)
        content = ""
    else:
        with open(filepath, "r", encoding="utf-8") as f:
            content = f.read()

    # Remove existing block if present
    pattern = re.compile(rf"{re.escape(BLOCK_MARKER_START)}.*?{re.escape(BLOCK_MARKER_END)}\n*", re.DOTALL)
    cleaned = pattern.sub("", content).rstrip()

    # Also clean any old standalone caveman block in opencode AGENTS.md
    cleaned = re.sub(r"<!-- caveman-begin -->.*?<!-- caveman-end -->\n*", "", cleaned, flags=re.DOTALL).rstrip()

    if add_block:
        new_content = cleaned + ("\n\n" if cleaned else "") + RULES_CONTENT + "\n"
    else:
        new_content = cleaned + "\n"

    with open(filepath, "w", encoding="utf-8") as f:
        f.write(new_content)
    return True

def set_hermes_rtk(enable=True):
    cfg = TARGETS["hermes"]["config"]
    if not os.path.exists(cfg):
        return
    with open(cfg, "r", encoding="utf-8") as f:
        lines = f.readlines()

    new_lines = []
    in_enabled = False
    has_rtk = False
    for line in lines:
        if line.strip() == "enabled:":
            in_enabled = True
        elif in_enabled and not line.startswith("    -"):
            if not has_rtk and enable:
                new_lines.append("    - rtk-rewrite\n")
            in_enabled = False
        
        if "- rtk-rewrite" in line:
            has_rtk = True
            if not enable:
                continue
        new_lines.append(line)

    with open(cfg, "w", encoding="utf-8") as f:
        f.writelines(new_lines)

def set_opencode_plugins(enable=True):
    cfg = TARGETS["opencode"]["config"]
    if not os.path.exists(cfg):
        return
    with open(cfg, "r", encoding="utf-8") as f:
        text = f.read()

    plugins = ["oh-my-opencode-slim"]
    if enable:
        plugins.extend(["./plugins/caveman", "./plugins/rtk.ts"])

    text = re.sub(r'"plugin"\s*:\s*\[.*?\]', f'"plugin": {json.dumps(plugins, indent=4)}', text, flags=re.DOTALL)
    with open(cfg, "w", encoding="utf-8") as f:
        f.write(text)

def set_antigravity(enable=True):
    if not os.path.exists(AGY_BIN):
        return
    action = "enable" if enable else "disable"
    for plugin in ["caveman", "ponytail", "rtk"]:
        run_cmd([AGY_BIN, "plugin", action, plugin])

def enable_all():
    print("🚀 Mengaktifkan Trio Skills (Caveman + Ponytail + RTK) untuk SEMUA AI AGENT...")
    # Antigravity
    set_antigravity(True)
    
    # All agent targets
    for key, tgt in TARGETS.items():
        update_file_block(tgt["file"], True)
        if tgt["type"] == "opencode":
            set_opencode_plugins(True)
        elif tgt["type"] == "hermes":
            set_hermes_rtk(True)

    print("✅ SEMUA SKILLS AKTIF OTOMATIS untuk:")
    print("   • Antigravity CLI (agy / anti)")
    print("   • Claude Code (claude)")
    print("   • OpenCode (opencode)")
    print("   • Hermes Agent (hermes)")
    print("   • Codex CLI (codex)")
    print("   • Cursor IDE (cursor)")
    print("   • Windsurf & Cline / Roo Code")

def disable_all():
    print("🛑 Menonaktifkan Trio Skills (Caveman + Ponytail + RTK) untuk SEMUA AI AGENT...")
    set_antigravity(False)
    for key, tgt in TARGETS.items():
        if os.path.exists(tgt["file"]):
            update_file_block(tgt["file"], False)
        if tgt["type"] == "opencode":
            set_opencode_plugins(False)
        elif tgt["type"] == "hermes":
            set_hermes_rtk(False)

    print("💤 SEMUA SKILLS DINONAKTIFKAN (Kembali ke Normal Mode) untuk semua agent.")

def show_status():
    print("📊 Status Trio Skills (Caveman, Ponytail, RTK) di Seluruh Agent:")
    
    # Antigravity
    if os.path.exists(AGY_BIN):
        code, out, _ = run_cmd([AGY_BIN, "plugin", "list"])
        agy_cav = "caveman (enabled)" in out
        agy_pon = "ponytail (enabled" in out
        agy_rtk = "rtk (enabled" in out
        status_str = "🟢 AKTIF" if (agy_cav and agy_pon and agy_rtk) else "🔴 NONAKTIF"
        print(f"  • Antigravity CLI (agy / anti) : {status_str}")
    else:
        print("  • Antigravity CLI (agy / anti) : ⚪ Tidak terpasang")

    # Other agents
    for key, tgt in TARGETS.items():
        parent_dir = os.path.dirname(tgt["file"])
        if os.path.exists(tgt["file"]) or os.path.exists(parent_dir):
            has_block = False
            if os.path.exists(tgt["file"]):
                with open(tgt["file"], "r", encoding="utf-8") as f:
                    has_block = BLOCK_MARKER_START in f.read()
            status_str = "🟢 AKTIF" if has_block else "🔴 NONAKTIF"
            print(f"  • {tgt['name']:<28} : {status_str}")
        else:
            print(f"  • {tgt['name']:<28} : ⚪ Tidak terpasang")

    print("\n💡 Perintah:")
    print("  agent-skills on      # Aktifkan semua agent")
    print("  agent-skills off     # Matikan semua agent")

def main():
    if len(sys.argv) < 2:
        show_status()
        sys.exit(0)

    arg = sys.argv[1].lower()
    if arg in ["on", "enable", "start", "1"]:
        enable_all()
    elif arg in ["off", "disable", "stop", "0"]:
        disable_all()
    elif arg in ["status", "check"]:
        show_status()
    else:
        print(f"Perintah tidak dikenal: {arg}")
        print("Gunakan: agent-skills on | off | status")
        sys.exit(1)

if __name__ == "__main__":
    main()
