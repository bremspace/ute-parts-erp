---
name: pasang-bre
description: >
  Pasang dan aktifkan semua skills, rules, plugins (Caveman, Ponytail, RTK),
  MCP, dan shortcuts di environment ini. Trigger ketika user berkata
  "pasang bre", "pasang brem", "/pasang-bre", atau "setup agent".
---

# Pasang Bre (Universal Agent Setup)

Skill ini digunakan untuk menginstal dan mengaktifkan seluruh ekosistem agent di repository ini (Antigravity, OpenCode, Hermes Agent, Claude Code, Cursor, dll.) agar langsung aktif dan siap pakai.

## Trigger Phrases
- "pasang bre"
- "pasang brem"
- "/pasang-bre"
- "setup environment agent"

## Prosedur Eksekusi
Ketika user memberikan perintah tersebut, jalankan perintah bash berikut:

```bash
bash scripts/pasang-bre.sh
```

Setelah script selesai dijalankan:
1. Pastikan akun GitHub telah diperiksa:
   - Jika akun adalah `bremspace` (Owner): Beri tahu user bahwa akses penuh ke branch `brem` dan `main` aktif.
   - Jika akun bukan `bremspace` (Kolaborator): Ingatkan bahwa user hanya diizinkan push ke branch `Unas` dan dilarang push ke `main`/`brem`.
   - Jika belum login: Minta user untuk menghubungkan akun via `gh auth login` atau SSH key.
2. Konfirmasi kepada user bahwa seluruh skills, rules, plugins, dan shortcuts telah berhasil terpasang dan aktif di semua AI agent harness.
3. Tampilkan status aktif dari `agent-skills status`.
4. Ingatkan shortcut `anti` dan cara menonaktifkannya jika diinginkan (`agent-skills off`).
