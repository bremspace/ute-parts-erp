# Panduan Integrasi Duitku Payment Gateway (Ute Parts ERP)

Dokumen ini berisi panduan konfigurasi manual untuk integrasi Duitku payment gateway pada lingkungan **Sandbox (Testing)** maupun **Production (Live)** di domain `uteparts.id`.

---

## 1. Konfigurasi Environment File (`.env`)

Edit file `.env` di server produksi (`/var/www/uteparts.id/.env`) atau staging:

### A. Mode Sandbox (Testing / Uji Coba)
Gunakan kredensial sandbox dari portal [sandbox.duitku.com](https://sandbox.duitku.com):

```ini
APP_URL=https://uteparts.id

# Duitku Gateway - Sandbox Mode
DUITKU_SANDBOX=true
DUITKU_MERCHANT_CODE=DS36712
DUITKU_API_KEY=d4a2abdb53c3615dba8ca975757a7c50
DUITKU_MERCHANT_KEY=d4a2abdb53c3615dba8ca975757a7c50
DUITKU_SANDBOX_URL=https://sandbox.duitku.com/webapi/api/merchant
DUITKU_PRODUCTION_URL=https://passport.duitku.com/webapi/api/merchant
DUITKU_CALLBACK_URL=https://uteparts.id/webhook/duitku
DUITKU_EXPIRY_PERIOD=1440
DUITKU_SIGNATURE_MODE=dual
```

### B. Mode Production (Live / Transaksi Riil)
Gunakan kredensial live resmi dari portal [passport.duitku.com](https://passport.duitku.com):

```ini
APP_URL=https://uteparts.id

# Duitku Gateway - Live Production Mode
DUITKU_SANDBOX=false
DUITKU_MERCHANT_CODE=DXXXXX
DUITKU_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
DUITKU_MERCHANT_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
DUITKU_SANDBOX_URL=https://sandbox.duitku.com/webapi/api/merchant
DUITKU_PRODUCTION_URL=https://passport.duitku.com/webapi/api/merchant
DUITKU_CALLBACK_URL=https://uteparts.id/webhook/duitku
DUITKU_EXPIRY_PERIOD=1440
DUITKU_SIGNATURE_MODE=dual
```

*Catatan: Setelah mengubah `.env`, jalankan `php artisan config:cache` di server.*

---

## 2. Pengaturan Dashboard Merchant Duitku

Masuk ke portal merchant:
- **Sandbox**: [sandbox.duitku.com](https://sandbox.duitku.com)
- **Live / Prod**: [passport.duitku.com](https://passport.duitku.com)

Buka menu **My Projects** / **Project Settings**, lalu sesuaikan:

1. **IP Whitelist**:
   - Daftarkan IP Publik VPS: `66.42.48.27`
   - *Penting: Duitku memblokir API request inquiry & create transaction jika IP asal tidak terdaftar di whitelist project.*
2. **Callback URL**:
   - `https://uteparts.id/webhook/duitku`
   - Gunakan protokol HTTPS port 443 (atau HTTP port 80). *Duitku tidak mendukung port kustom seperti :8080*.
3. **Return URL**:
   - `https://uteparts.id/account`
   - Halaman tujuan setelah pelanggan menyelesaikan pembayaran di payment page Duitku.
4. **Metode Notifikasi / Notification Type**:
   - Pilih `HTTP POST`.

---

## 3. Konfigurasi Cloudflare / WAF (Production)

Jika domain `uteparts.id` menggunakan Cloudflare atau Reverse Proxy WAF:
1. Buka dashboard Cloudflare: **Security** → **WAF** → **Custom Rules**.
2. Buat rule baru:
   - **Rule Name**: `Bypass Webhook Duitku`
   - **Field**: `URI Path`
   - **Operator**: `equals`
   - **Value**: `/webhook/duitku`
   - **Action**: `Skip` / `Bypass` (WAF Components, Managed Rules, Rate Limiting).
3. Langkah ini wajib dilakukan agar request callback server-to-server dari IP Duitku tidak dicegat oleh captcha Cloudflare (error 403 / 503).

---

## 4. Alur & Fitur Unggulan Integrasi

1. **Segmented Tabs Modal Pembayaran**:
   - Metode pembayaran otomatis dikelompokkan ke dalam tab: QRIS, Virtual Account, E-Wallet, Retail, dan Kartu.
   - Metode yang bermasalah di sandbox secara otomatis difilter keluar.
2. **Auto-Resolving Notifications**:
   - Saat pembayaran terkonfirmasi lunas, notifikasi *"menunggu pembayaran"* lama milik staf dan konsumen otomatis ditandai selesai tanpa perlu konfirmasi manual.
   - Notifikasi baru *"Pembayaran Diterima"* otomatis diterbitkan untuk tim gudang dan konsumen.
3. **Tombol "Saya Sudah Bayar — Cek Status"**:
   - Endpoint `POST /api/payment/check-status` melakukan pengecekan live ke server Duitku secara on-demand. Jika status di gateway sudah berhasil, pesanan langsung diubah menjadi lunas, stok dipotong, dan auto-jurnal dibuat secara atomik (idempoten & race-condition safe).
4. **Simulator Sandbox**:
   - Untuk simulasi pelunasan Virtual Account di mode sandbox, buka:
     `https://sandbox.duitku.com/payment/demo/demosuccesstransaction.aspx`
   - Masukkan nomor VA yang tertera pada modal pembayaran, lalu klik submit untuk memicu status lunas.
