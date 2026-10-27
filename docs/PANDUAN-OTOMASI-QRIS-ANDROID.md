# Panduan Otomasi Deteksi Pembayaran QRIS Toko via Notifikasi Android

Dokumen ini berisi panduan lengkap langkah demi langkah untuk mengonfigurasi forwarder notifikasi m-Banking/e-Wallet di HP Android kasir toko ke server Ute Parts ERP.

Sistem ini memungkinkan kasir mendeteksi pembayaran QRIS dinamis secara otomatis dan real-time menjadi status **LUNAS** tanpa perlu biaya transaksi Payment Gateway (Zero Transaction Fee).

---

## 1. Arsitektur & Cara Kerja

```
[Konsumen Scan & Bayar QRIS Dinamis di Kasir POS]
                         │
                         ▼
       [Uang Masuk ke Rekening / QRIS Toko]
                         │
                         ▼
     [Push Notifikasi Masuk ke HP Android Toko]
    (BCA Mobile / Livin' Mandiri / BRImo / GoPay /
          OVO / DANA / SMS Banking dll)
                         │
                         ▼
      [Aplikasi Forwarder Android: MacroDroid]
   Membaca isi pesan notifikasi & memicu HTTP POST / GET
                         │
                         ▼
     [Server ERP: POST/GET /api/webhook/qris-notif]
  Mengekstrak nominal via regex & potong desimal sen (,00)
                         │
                         ▼
           [Layar Kasir POS (Polling 3s)]
    Banner Hijau: "PEMBAYARAN TERDETEKSI LUNAS!"
      Transaksi Otomatis Diselesaikan & Struk Siap Cetak
```

---

## 2. Persiapan di ERP Backoffice

1. Masuk ke Backoffice ERP Ute Parts sebagai Administrator / Superadmin.
2. Buka menu **Pengaturan > QRIS Toko**.
3. Pastikan Foto QRIS Statis Toko sudah diunggah dan ter-parse dengan benar.
4. Perhatikan kartu **Otomasi Deteksi Lunas (Tanpa PG)** di sisi kanan:
   - **Webhook URL**:
     ```text
     https://test.uteparts.id/api/webhook/qris-notif
     ```
     *(Ganti domain sesuai domain aktif production Anda jika sudah live).*
   - **Secret Key Webhook**:
     Tentukan token rahasia untuk mencegah spam/pembajakan (contoh: `uteparts_rahasia_2026`).
5. Klik tombol **Simpan Pengaturan QRIS**.

---

## 3. Konfigurasi di HP Android Kasir (Menggunakan MacroDroid)

**MacroDroid** adalah aplikasi otomasi gratis di Google Play Store yang sangat stabil, ringan, dan tidak boros baterai.

### Langkah 3.1: Unduh & Izin Aplikasi
1. Buka **Google Play Store** di HP Android kasir yang menerima SMS / notifikasi m-Banking.
2. Cari dan instal **MacroDroid - Device Automation**.
3. Buka aplikasi MacroDroid dan berikan 2 izin vital:
   - **Akses Notifikasi (Notification Access)**: Wajib diizinkan agar MacroDroid bisa membaca teks notifikasi bank saat masuk.
   - **Abaikan Optimasi Baterai (Battery Optimization)**: Pilih *Jangan Batasi* / *Unrestricted* agar proses MacroDroid tidak ditutup oleh sistem Android saat layar HP mati.

---

### Langkah 3.2: Membuat Makro Baru
1. Di layar utama MacroDroid, klik tombol **Add Macro** (ikon +).
2. Di bagian atas, beri nama makro: `Forwarder QRIS Kasir`.

---

### Langkah 3.3: Menambahkan Pemicu (Trigger)
1. Pada blok warna merah (**Triggers**), klik tombol **+**.
2. Pilih kategori **Device Events** > pilih **Notification**.
3. Pilih opsi **Notification Received** > klik **OK**.
4. Pilih **Select Applications** > klik **OK**.
5. Centang aplikasi perbankan / e-wallet toko Anda, misalnya:
   - *BCA mobile*
   - *Livin' by Mandiri*
   - *BRImo*
   - *GoPay Merchant*
   - *OVO*
   - *DANA*
   - *Pesan / Messages* (jika menggunakan SMS banking)
6. Pada dialog *Text Content*:
   - Pilih opsi **Any content** (agar semua notifikasi dari aplikasi tersebut terbaca).
7. Klik **OK**.

---

### Langkah 3.4: Menambahkan Tindakan (Action)
1. Pada blok warna biru (**Actions**), klik tombol **+**.
2. Pilih kategori **Applications** > pilih **HTTP Request**.
3. Atur parameter HTTP Request. Anda dapat memilih salah satu dari 2 metode di bawah:

#### Opsi A: Format Form URL-Encoded (Sangat Direkomendasikan — Paling Tahan Karakter Khusus)
Format ini paling stabil karena tidak akan error meskipun notifikasi bank mengandung tanda kutip (`"`), enter (`\n`), atau karakter unik lainnya:

| Parameter | Nilai Pengaturan |
| :--- | :--- |
| **Request Method** | `POST` |
| **URL** | `https://test.uteparts.id/api/webhook/qris-notif` |
| **Content Type** | `application/x-www-form-urlencoded` |
| **Request Body (Text)** | `title=[not_title]&text=[not_body]&secret=uteparts_rahasia_2026` |

#### Opsi B: Format JSON
| Parameter | Nilai Pengaturan |
| :--- | :--- |
| **Request Method** | `POST` |
| **URL** | `https://test.uteparts.id/api/webhook/qris-notif` |
| **Content Type** | `application/json` |
| **Request Body (Text)** | Lihat format JSON di bawah |

Isi **Request Body (Text)**:
```json
{
  "title": "[not_title]",
  "text": "[not_body]",
  "secret": "uteparts_rahasia_2026"
}
```
*(Catatan: `[not_title]` dan `[not_body]` adalah tag variabel otomatis MacroDroid yang dapat dipilih via tombol titik tiga `...`).*

4. Atur **Header Parameter** (Opsional jika secret sudah di body):
   - Klik **Add Header**.
   - Header Name: `X-QRIS-Secret`
   - Header Value: `uteparts_rahasia_2026` *(samakan dengan Secret Key di menu Pengaturan ERP)*.
5. Klik tombol centang / simpan aksi.

---

### Langkah 3.5: Simpan & Aktifkan Makro
1. Klik tombol centang / simpan di pojok kanan bawah layar MacroDroid.
2. Pastikan tombol saklar makro dalam posisi **Aktif (ON)**.

---

## 4. Opsi Alternatif: Aplikasi Webhook Forwarder Lain

Jika Anda menggunakan aplikasi forwarder notifikasi lain dari Play Store (seperti *Notification to Webhook* atau *Push to Webhook*):

- **Target URL**: `https://test.uteparts.id/api/webhook/qris-notif`
- **HTTP Method**: `POST`
- **Headers**:
  ```http
  Content-Type: application/json
  X-QRIS-Secret: uteparts_rahasia_2026
  ```
- **Payload Data**:
  Sistem ERP Ute Parts mendukung berbagai variasi field JSON:
  - `{ "nominal": 75000 }` (jika nominal sudah berupa angka).
  - `{ "text": "Dana masuk Rp 75.000 dari QRIS..." }` (sistem otomatis mengekstrak nominal via regular expression).
  - `{ "title": "BCA Mobile", "body": "Transaksi QRIS Rp 75.000 berhasil" }`.

---

## 5. Pengujian & Verifikasi Alur

### Uji Coba 1: Simulasi Webhook via Terminal (cURL)
Jalankan perintah ini di komputer / terminal untuk menguji apakah endpoint ERP siap menerima data:

```bash
curl -X POST https://test.uteparts.id/api/webhook/qris-notif \
  -H "Content-Type: application/json" \
  -H "X-QRIS-Secret: uteparts_rahasia_2026" \
  -d '{"title": "BCA Mobile", "text": "Transaksi QRIS Rp 50.000 dari BUDI SANTOSO berhasil."}'
```

**Hasil respon yang benar:**
```json
{
  "success": true,
  "message": "Notifikasi QRIS berhasil diproses.",
  "data": {
    "nominal": 50000,
    "title": "BCA Mobile",
    "text": "Transaksi QRIS Rp 50.000 dari BUDI SANTOSO berhasil.",
    "waktu": "2026-10-27T10:00:00+07:00"
  }
}
```

---

### Uji Coba 2: Transaksi Nyata di Layar Kasir POS

1. Buka layar kasir **Kasir POS** (`/app/pos`).
2. Masukkan produk ke keranjang belanja (misal total: **Rp 50.000**).
3. Klik tombol **Bayar** (F9), lalu pilih metode **QRIS** (atau **Split** dengan porsi non-tunai QRIS Rp 50.000).
4. Layar kasir akan menampilkan:
   - QRIS Dinamis yang otomatis mengunci nominal Rp 50.000.
   - Tombol **🔍 Layar Penuh (Popup)** untuk menampilkan QR berukuran besar ke hadapan konsumen.
   - Tombol **📥 Unduh PNG** dan **📲 Kirim WA** jika ingin mengirim rincian tagihan ke ponsel pelanggan.
5. Lakukan pembayaran riil atau jalankan simulasi cURL di atas.
6. Dalam 1–3 detik setelah notifikasi masuk ke HP kasir:
   - Sistem mendeteksi pelunasan QRIS Rp 50.000.
   - Banner hijau muncul dan transaksi **OTOMATIS DISELESAIKAN** oleh sistem tanpa kasir perlu klik tombol bayar lagi.
   - Layar langsung menampilkan struk pembayaran yang siap dicetak via printer thermal atau dikirim ke pelanggan.

Hal yang sama berlaku untuk pembayaran **Tiket Servis** di modal servis kasir: saat status lunas QRIS terdeteksi, tiket servis langsung diselesaikan secara otomatis.

---

## 6. Tips Keandalan & Troubleshooting

1. **HP Kasir Mengabaikan Notifikasi saat Layar Mati**:
   - Buka *Pengaturan HP > Aplikasi > MacroDroid*.
   - Matikan fitur *Hemat Daya Otomatis (Battery Saver)* untuk MacroDroid.
   - Aktifkan fitur *Mulai Otomatis (Autostart)* dan kunci aplikasi di Task Manager / Recent Apps (ikon gembok).
2. **Koneksi Jaringan**:
   - Pastikan HP Android kasir selalu tersambung ke jaringan Wi-Fi toko atau memiliki kuota data aktif.
3. **Format Notifikasi Bank Baru**:
   - Parser ERP mengenali format umum seperti `Rp 50.000`, `Rp. 50.000`, `IDR 50.000`, atau kalimat `sebesar 50.000`.
   - Jika bank menggunakan format teks unik, Anda dapat menginput langsung parameter `"nominal": 50000` di JSON body MacroDroid.
4. **Fallback Manual**:
   - Jika HP kasir mati atau kehabisan baterai, kasir tetap dapat melihat bukti transfer pelanggan dan menekan tombol **Cek Status** atau langsung konfirmasi transaksi seperti biasa.
