# OpenWA — WhatsApp Gateway Self-Hosted (deploy)

Integrasi WhatsApp untuk Bremspace ERP. OpenWA adalah gateway REST self-hosted
(open source, MIT) yang dipasang di VPS yang sama dan diakses Laravel lewat
`http://127.0.0.1:2785/api`.

> **Baca dulu — ini bukan API resmi WhatsApp.**
> OpenWA memakai klien reverse-engineered (whatsapp-web.js / baileys), bukan
> Meta Cloud API. Konsekuensinya: **ada risiko nomor dibatasi/diblokir**,
> dan untuk lingkungan komersial/teratur sebaiknya memakai
> [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api).
> Keputusan memakai OpenWA harus disadari risikonya.

## 1. Prasyarat

- Docker engine (sudah ada di VPS ini: 29.x).
- Plugin `docker compose` v2 — **belum ada** di VPS ini. Pasang dengan:
  ```bash
  sudo bash deploy/openwa/install-compose.sh
  ```
- Nomor WhatsApp **khusus** yang siap dipakai gateway (jangan nomor pribadi/
  utama). Nomor ini yang nanti discan QR-nya.

## 2. Jalankan

```bash
cd deploy/openwa
cp .env.example .env          # sesuaikan ENGINE_TYPE & batas memori
docker compose up -d
docker compose logs -f openwa-api
```

Ambil API key admin sekali jadi (dicetak di log, juga tersimpan di dalam container):

```bash
docker exec openwa-api cat /app/data/.api-key
```

## 3. Buat sesi & pairing nomor

Buka dashboard di `http://127.0.0.1:2785` (hanya bisa dari dalam VPS, mis. lewat
SSH tunnel: `ssh -L 2785:127.0.0.1:2785 user@vps`).

1. Masuk dengan API key admin.
2. Buat sesi (nama default: `default`).
3. Start sesi → scan QR dengan **nomor khusus** tadi.
4. Setelah status connected, buat API key baru ber-role **operator**, scope ke
   sesi `default` (jangan pakai key admin untuk aplikasi).
5. Salin key operator itu.

## 4. Hubungkan ke ERP

Di ERP: **Pengaturan → Integrasi → WhatsApp Gateway**

- Provider: `OpenWA (Self-Hosted Gateway)`
- API Endpoint URL: `http://127.0.0.1:2785/api`
- Token / API Key: key **operator** dari langkah 3
- ID Sesi OpenWA: `default`
- Klik **Uji Coba WhatsApp** untuk memvalidasi, lalu Simpan.

Atau lewat `.env` ERP (fallback bila belum diatur di UI):

```
WA_GATEWAY_URL=http://127.0.0.1:2785/api
WA_GATEWAY_TOKEN=<api-key-operator>
WA_GATEWAY_DEVICE_ID=default
```

## 5. Keamanan (wajib dipatuhi)

- **Bind loopback**: compose mempublikasikan `127.0.0.1:2785` saja. Jangan ubah
  ke `0.0.0.0` tanpa reverse proxy + TLS; dashboard dan API key travel plaintext.
- **Key operator session-scoped**, bukan admin. Kalau bocor, dampaknya terbatas
  ke satu sesi.
- **Nomor khusus**, dihangatkan dulu (warm-up) beberapa hari: jangan blast
  hari pertama. `SEND_PACING_*` sudah aktif di `.env` (batas kirim per umur sesi
  + batas sapaan ke kontak baru).
- **Jangan logout** sesi yang sudah berfungsi. Link ulang bisa menabrak gerbang
  passkey WhatsApp dan membuat sesi tak bisa dipasang lagi.
- Volume `openwa_openwa-data` berisi **kredensial WA dan API key** (plaintext).
  Jangan ikut dibackup ke lokasi tak terkunci; backup terpisah & terenkripsi.
- OpenWA **tidak** dipakai untuk alur kritis (OTP login). Untuk itu tetap pakai
  jalur CS/aktivasi seperti sekarang.

## 6. Operasi

```bash
docker compose ps
docker compose logs -f openwa-api
docker compose restart openwa-api
docker compose pull openwa-api && docker compose up -d   # upgrade (set OPENWA_VERSION dulu)
docker exec openwa-api cat /app/data/.api-key            # key admin (setelah boot pertama hanya ter-mask)
```

Rollback: ubah provider WA di UI ERP ke Fonnte/Wablas/Custom (atau kosongkan
token) — OpenWA bisa berhenti kapan saja tanpa memengaruhi ERP.

## 7. Webhook masuk (opsional)

OpenWA bisa mengirim event (mis. `message.received`) ke ERP:

```bash
curl -X POST http://127.0.0.1:2785/api/sessions/default/webhooks \
  -H "Content-Type: application/json" -H "X-API-Key: <operator-key>" \
  -d '{"url":"https://tokobaru.com/webhook/openwa","events":["message.received"],"secret":"<hmac-secret>"}'
```

Endpoint ERP terkait belum dibangun — lihat catatan di `docs/` bila akan
diterapkan (verifikasi HMAC wajib).
