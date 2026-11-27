# Panduan Operasional Kasir: Mode Offline Mandiri & Failover Cloud
**Sistem:** Bremspace ERP — Arsitektur Hybrid Database Sync (Offline-Ready & Cloud Failover)  
**Dokumen Referensi:** [ADR 0013](file:///var/www/ute-parts-erp/docs/adr/0013-hybrid-database-sync-architecture.md) & [Rencana Kerja](file:///var/www/ute-parts-erp/docs/plans/2026-11-23-hybrid-database-sync-plan.md)  
**Sasaran Pembaca:** Kasir, Front Office Servis, Kepala Toko, dan Staf Non-IT  

---

## 1. Mengenal Sistem Kasir Bremspace ERP

Aplikasi kasir Bremspace ERP menggunakan teknologi **Hybrid Database Sync** (mirip prinsip kerja mesin POS modern seperti SID Retail Pro). 

Artinya:
* **Sangat Cepat (< 30 milidetik)**: Semua transaksi, scan barcode, dan cetak nota diproses langsung di harddisk PC toko Anda, bukan menunggu koneksi internet ke server luar.
* **Tahan Internet Mati**: Jika kabel internet putus atau sinyal modem gangguan, kasir **TIDAK AKAN BERHENTI**. Anda tetap bisa melayani antrean pembeli, scan barcode, dan cetak struk thermal tanpa gangguan sedikit pun.
* **Otomatis Sinkron**: Begitu internet toko tersambung kembali, sistem di PC toko akan secara otomatis mengirimkan rekapan penjualan ke server Cloud pusat (VPS).

---

## 2. Mengenal 3 Warna Lampu Indikator Kasir

Di pojok atas layar kasir (POS) dan papan servis, terdapat tombol lampu indikator status sinkronisasi:

| Lampu Indikator | Arti Status | Tindakan Kasir |
|---|---|---|
| 🟢 **Online & Sinkron** | Internet lancar dan seluruh nota kasir sudah tersimpan rapi di Cloud pusat. | Lanjutkan pelayanan seperti biasa. Tidak perlu melakukan apa-apa. |
| 🟡 **Menyimpan di PC (X Antrean)** | Internet toko sedang lambat atau baru saja terhubung kembali. Transaksi tersimpan aman di PC toko dan sedang dalam antrean pengiriman ke Cloud. | Tetap jualan dengan santai. Data Anda dijamin tidak akan hilang. Antrean akan terkirim secara otomatis. |
| 🔴 **Mode Toko Mandiri (Offline)** | Internet toko sedang mati atau kabel LAN terlepas. | **Jangan panik!** Tetap layani pembeli, scan barcode barang, dan cetak struk nota seperti biasa. Sistem berjalan penuh secara mandiri di PC toko. |

---

## 3. Cara Mempercepat Pengiriman Data (Tombol "Sinkronkan Sekarang")

Sistem kasir secara otomatis mencoba mengirim data setiap 1 menit. Namun, jika internet toko baru saja menyala dan Anda ingin memastikan data langsung terkirim seketika:

1. Klik tombol lampu indikator di pojok layar kasir.
2. Jendela status kecil akan terbuka.
3. Klik tombol biru **"Sinkronkan Sekarang"**.
4. Lampu indikator akan berputar sebentar, lalu berubah menjadi warna hijau 🟢 **Online & Sinkron**.

---

## 4. Prosedur Darurat Bencana: Apa yang Harus Dilakukan Jika PC Toko Mati Total? (Failover ke Cloud)

Jika terjadi keadaan darurat di toko (misalnya: PC kasir mati total, tersambar petir, korsleting listrik, atau terbakar), toko **TETAP BISA BUKA** menggunakan perangkat cadangan (HP, tablet, atau laptop apa pun).

### Langkah-langkah Beralih ke Cloud (Failover):
1. **Ambil Perangkat Cadangan**: Gunakan laptop staf lain, tablet kasir, atau HP Android/iPhone. Hubungkan ke jaringan internet (Wi-Fi atau kuota hotspot HP).
2. **Buka Browser**: Buka Google Chrome, Safari, atau Firefox.
3. **Ketik Alamat Cloud ERP Toko**:
   Buka alamat Cloud VPS toko Anda (misalnya: `https://vps.bremspace.id/app/pos`).
4. **Login**: Masukkan email dan password kasir Anda seperti biasa.
5. **Layani Pelanggan Normal**:
   - Di mode Cloud darurat ini, nomor nota kasir otomatis menggunakan awalan **`VPS-...`** (misalnya `VPS-C01-20261123-0001`).
   - Struk tetap bisa dicetak via Bluetooth thermal printer atau printer kasir jaringan.
6. **Pemulihan Otomatis Saat PC Toko Selesai Diperbaiki**:
   - Ketika PC kasir toko yang rusak sudah selesai diperbaiki atau diganti baru dan dinyalakan kembali, sistem di PC toko akan secara otomatis mendeteksi transaksi darurat `VPS-...` dari cloud dan menyerapnya ke database lokal toko (`sync:pull`).
   - Saldo stok gudang dan pembukuan keuangan akan langsung cocok kembali secara sempurna tanpa perlu input ulang manual.

---

## 5. Pertanyaan Umum Kasir (FAQ)

### Q: Apakah saya bisa jualan seharian jika internet toko mati total dari pagi sampai malam?
**A:** Sangat bisa! Database tersimpan di PC toko lokal. Anda bisa melakukan ratusan transaksi, scan ribuan barang, dan cetak struk tanpa internet. Saat malam hari internet menyala, semua ratusan transaksi tersebut akan langsung terkirim ke Cloud pusat.

### Q: Jika Bos/Owner menambah produk baru atau mengubah harga dari rumah melalui web Cloud saat PC toko mati, kapan kasir menerima harga baru tersebut?
**A:** Begitu PC toko dinyalakan dan internet tersambung, PC toko akan otomatis menarik data produk dan harga baru tersebut dalam hitungan detik. Di kasir langsung muncul harga baru.

### Q: Apakah klaim garansi dan retur barang tetap bisa dilakukan untuk nota berawalan `POS-` dan `VPS-`?
**A:** Ya! Sistem kasir mengenali semua nota baik yang dibuat saat toko offline (`POS-...`), transaksi darurat cloud (`VPS-...`), maupun transaksi biasa (`TRX-...`). Cukup ketik nomor nota atau scan barcode pada struk.

### Q: Bagaimana jika muncul pesan "Gagal Sinkron" berwarna merah?
**A:** Periksa modem internet atau kabel LAN di toko Anda. Jika internet komputer sudah bisa membuka website lain (seperti google.com), cukup klik tombol **"Sinkronkan Sekarang"**. Jika masih kendala, hubungi bagian teknisi/IT dengan menyebutkan isi pesan di layar.

---

## 6. Pilihan Pemasangan di PC Toko (Docker Installer vs Script Standalone)

Untuk memasang Bremspace ERP di PC Toko Lokal, tersedia dua metode:

### 1. Metode Rekomendasi: Docker Installer (Folder `installers/docker/`)
* **Keunggulan**: Sangat bersih, tidak perlu instalasi manual PHP, Composer, atau MySQL di Windows.
* **Cara Pakai**:
  1. Pastikan **Docker Desktop** sudah terpasang dan berjalan di PC toko.
  2. Masuk ke folder `installers/docker/` dan klik dua kali **`install.bat`** (atau jalankan via PowerShell `install.ps1`, atau `bash install.sh` di Linux/macOS).
  3. Installer otomatis mem-build image, membuat database MySQL lokal di volume persistent, membuka akses firewall port 80 untuk PC kasir lain di LAN, dan menyalakan background queue worker (`sync`) serta scheduler.
  4. Buka kasir via browser di `http://localhost/app/pos`.

### 2. Metode Standalone (Folder `installers/local/`)
* **Penggunaan**: Untuk PC toko yang sudah memiliki runtime PHP 8.2+ dan MySQL/SQLite native (misal Laragon / Linux Ubuntu).
* **File**:
  - Linux / macOS / WSL: `installers/local/linux/setup-local-store-node.sh`
  - Windows: `installers/local/windows/setup-local-store-node.bat`
* **Cara Pakai**: Cukup jalankan skrip tersebut, sistem akan menyiapkan file `.env`, memigrasikan database lokal, dan membuat shortcut launcher desktop `Buka-Kasir.bat`.
