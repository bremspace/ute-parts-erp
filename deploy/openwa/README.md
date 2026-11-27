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

- Docker engine (VPS ini: `29.x`) & plugin `docker compose` v2 (`v2.39.x`, sudah terpasang). Bila setup di server baru yang belum memiliki plugin compose:
  ```bash
  sudo bash deploy/openwa/install-compose.sh
  ```
- Nomor WhatsApp **khusus** yang siap dipakai gateway (jangan nomor pribadi/
  utama). Nomor ini yang nanti discan QR-nya.

## 2. Jalankan kontainer (1x di terminal VPS)

```bash
cd deploy/openwa
cp .env.example .env          # sesuaikan ENGINE_TYPE & batas memori
docker compose up -d
docker compose logs -f openwa-api
```

Ambil Master Admin Key sekali jadi dari dalam kontainer:

```bash
docker exec openwa-api cat /app/data/.api-key
```

## 3. Setup Sesi, API Key Operator, dan Scan QR via UI ERP

Tidak perlu SSH tunnel ke dasbor OpenWA. Buka ERP: **Pengaturan → Integrasi → WhatsApp Gateway**

1. Pilih Provider: `OpenWA (Self-Hosted Gateway)`.
2. Pastikan API Endpoint URL: `http://127.0.0.1:2785/api`.
3. Pada panel **Setup Otomatis 1-Klik**, tempel Master Admin Key dari langkah 2, lalu klik **Hubungkan & Buat Sesi Otomatis**.
   - ERP otomatis memeriksa `/api/health/ready`, membuat sesi (mendapat **UUID** `sessionId`), menyalakan engine, mencetak API Key ber-role `operator` scoped ke sesi tersebut, menyimpannya terenkripsi di DB, dan menghapus Admin Key dari form.
4. Scan QR Code yang tampil di UI lewat WhatsApp HP (**Perangkat Tertaut → Tautkan Perangkat**), atau gunakan tab **Kode Pairing 8 Karakter**.
5. Setelah status menjadi **Terhubung & Siap**, isi nomor uji coba lalu klik **Kirim Pesan Uji Coba**.

> Catatan teknis: OpenWA memakai **UUID** `sessionId` (bukan nama sesi) pada path API `/api/sessions/{sessionId}/...`. Setup otomatis dari UI sudah mengisi UUID tersebut secara tepat.

Atau lewat `.env` ERP (fallback bila belum diatur di UI):

```
WA_OPENWA_URL=http://127.0.0.1:2785/api
WA_OPENWA_API_KEY=<api-key-operator>
WA_OPENWA_SESSION=<uuid-sesi>
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
